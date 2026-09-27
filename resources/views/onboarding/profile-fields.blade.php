<div class="grid">
@foreach(['name'=>'Company name','registration_number'=>'Registration number','license_number'=>'Waste-management licence number','email'=>'Company email','phone'=>'Company phone','website'=>'Website (optional)'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'website' ? 'url' : 'text') }}" value="{{ old($field, isset($company) ? $company->$field : '') }}" @required(in_array($field,['name','email','phone'])) maxlength="{{ $field === 'name' ? 180 : ($field === 'phone' ? 40 : 255) }}"></label>
@endforeach
</div>