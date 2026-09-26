<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function checks(): array
    {
        return [
            'company_locations' => "(NEW.active_to IS NOT NULL AND NEW.active_to <= NEW.active_from) OR NEW.status NOT IN ('active', 'closed')",
            'company_memberships' => "(NEW.left_at IS NOT NULL AND NEW.left_at < NEW.joined_at) OR NEW.status NOT IN ('active', 'inactive', 'suspended')",
            'company_service_areas' => "(NEW.active_to IS NOT NULL AND NEW.active_to <= NEW.active_from) OR NEW.status NOT IN ('active', 'inactive')",
            'property_company_assignments' => 'NEW.assigned_to IS NOT NULL AND NEW.assigned_to <= NEW.assigned_from',
            'property_occupancies' => "(NEW.move_out_date IS NOT NULL AND NEW.move_out_date <= NEW.move_in_date) OR NEW.occupancy_type NOT IN ('tenant', 'owner_occupier', 'other')",
            'service_plans' => "(((NEW.amount < 0 OR (NEW.effective_from IS NOT NULL AND NEW.effective_to IS NOT NULL AND NEW.effective_to <= NEW.effective_from)) OR NEW.property_type NOT IN ('residential', 'commercial', 'industrial', 'mixed_use')) OR NEW.billing_cycle NOT IN ('monthly', 'quarterly', 'annually', 'one_off')) OR NEW.status NOT IN ('active', 'inactive')",
            'bills' => "(NEW.subtotal < 0 OR NEW.discount_amount < 0 OR NEW.late_fee < 0 OR NEW.total_amount < 0 OR NEW.amount_paid < 0 OR NEW.outstanding_amount < 0 OR NEW.billing_period_end < NEW.billing_period_start OR ABS(NEW.total_amount - (NEW.subtotal - NEW.discount_amount + NEW.late_fee)) > 0.001 OR ABS(NEW.outstanding_amount - (NEW.total_amount - NEW.amount_paid)) > 0.001 OR (NEW.status <> 'draft' AND NEW.issued_at IS NULL)) OR NEW.status NOT IN ('draft', 'issued', 'partially_paid', 'paid', 'void')",
            'payments' => "((NEW.amount <= 0) OR NEW.payment_method NOT IN ('card', 'bank_transfer', 'cash', 'ussd', 'other')) OR NEW.status NOT IN ('pending', 'successful', 'failed', 'cancelled', 'reversed')",
            'receipts' => "NOT EXISTS (SELECT 1 FROM payments WHERE payments.id = NEW.payment_id AND payments.status = 'successful')",
            'collection_schedules' => "(NEW.day_of_week > 6 OR NEW.day_of_week < 0 OR NEW.recurrence_weeks < 1) OR NEW.status NOT IN ('active', 'paused', 'cancelled')",
            'complaint_messages' => '(NEW.sender_user_id IS NULL AND NEW.sender_resident_id IS NULL) OR (NEW.sender_user_id IS NOT NULL AND NEW.sender_resident_id IS NOT NULL)',
            'communities' => "NEW.status NOT IN ('active', 'inactive')",
            'companies' => "NEW.status NOT IN ('draft', 'pending_review', 'correction_required', 'approved', 'rejected', 'suspended')",
            'company_documents' => "(NEW.document_type NOT IN ('registration_certificate', 'waste_management_license', 'tax_certificate', 'other')) OR NEW.status NOT IN ('pending', 'approved', 'rejected')",
            'company_approval_logs' => "((NEW.action NOT IN ('submitted', 'approved', 'rejected', 'correction_requested', 'suspended', 'reinstated')) OR NEW.from_status NOT IN ('draft', 'pending_review', 'correction_required', 'approved', 'rejected', 'suspended')) OR NEW.to_status NOT IN ('draft', 'pending_review', 'correction_required', 'approved', 'rejected', 'suspended')",
            'staff_invitations' => "NEW.status NOT IN ('pending', 'accepted', 'expired', 'revoked')",
            'properties' => "(NEW.property_type NOT IN ('residential', 'commercial', 'industrial', 'mixed_use')) OR NEW.status NOT IN ('active', 'inactive')",
            'residents' => "NEW.status NOT IN ('active', 'inactive')",
            'collection_zones' => "NEW.status NOT IN ('active', 'inactive')",
            'collection_records' => "NEW.status NOT IN ('collected', 'missed', 'inaccessible', 'cancelled')",
            'complaints' => "(NEW.priority NOT IN ('low', 'normal', 'high', 'urgent')) OR NEW.status NOT IN ('open', 'in_progress', 'resolved', 'closed')",
            'users' => "NEW.status NOT IN ('active', 'inactive', 'suspended')",
        ];
    }

    public function up(): void
    {
        foreach ($this->checks() as $table => $condition) {
            foreach (['insert', 'update'] as $event) {
                // Reversals may follow receipt issuance; updating its file must still be possible.
                if ($table === 'receipts' && $event === 'update') {
                    continue;
                }
                $this->trigger("p1_{$table}_{$event}", $table, $event, $condition);
            }
        }

        foreach (['update', 'delete'] as $event) {
            $this->trigger("p1_approval_{$event}", 'company_approval_logs', $event, '1 = 1');
        }

        foreach (['bills', 'payments', 'receipts'] as $table) {
            $this->trigger("p1_{$table}_delete", $table, 'delete', '1 = 1');
        }

        $same = DB::getDriverName() === 'sqlite' ? 'IS' : '<=>';
        $billFields = ['company_id', 'property_occupancy_id', 'invoice_number', 'billing_period_start',
            'billing_period_end', 'subtotal', 'discount_amount', 'late_fee', 'total_amount', 'currency',
            'issued_at', 'resident_snapshot', 'property_snapshot', 'company_snapshot', 'plan_snapshot'];
        $changed = implode(' OR ', array_map(fn ($field) => "NOT (NEW.{$field} {$same} OLD.{$field})", $billFields));
        $this->trigger('p1_bill_immutable', 'bills', 'update', "OLD.status <> 'draft' AND ({$changed} OR NEW.status = 'draft')");

        $receiptFields = ['company_id', 'payment_id', 'receipt_number', 'generated_at', 'snapshot'];
        $changed = implode(' OR ', array_map(fn ($field) => "NOT (NEW.{$field} {$same} OLD.{$field})", $receiptFields));
        $this->trigger('p1_receipt_immutable', 'receipts', 'update', $changed);
    }

    private function trigger(string $name, string $table, string $event, string $condition): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, 'Phase 1 invariant: {$name}'); END");
        } else {
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phase 1 invariant: {$name}'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->checks()) as $table) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS p1_{$table}_{$event}");
            }
        }
        foreach (['p1_approval_update', 'p1_approval_delete', 'p1_bills_delete', 'p1_payments_delete',
            'p1_receipts_delete', 'p1_bill_immutable', 'p1_receipt_immutable'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
    }
};
