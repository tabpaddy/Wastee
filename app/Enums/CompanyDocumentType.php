<?php

namespace App\Enums;

enum CompanyDocumentType: string
{
    case RegistrationCertificate = 'registration_certificate';
    case WasteManagementLicense = 'waste_management_license';
    case TaxCertificate = 'tax_certificate';
    case Other = 'other';
}
