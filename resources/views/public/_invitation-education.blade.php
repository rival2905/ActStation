@if(in_array($invitationContext['identity_type'], ['nisn', 'nim'], true))
<div class="mb-3">
    <label class="form-label" for="education_detail">{{ $invitationContext['identity_type'] === 'nim' ? 'Prodi / Fakultas' : 'Kelas / Jurusan' }}</label>
    <input class="form-control @error('education_detail') is-invalid @enderror" id="education_detail" name="education_detail" type="text" maxlength="150" value="{{ old('education_detail', $educationDetail ?? '') }}" aria-describedby="education_detail-help education_detail-error">
    <small class="form-text d-block" id="education_detail-help">Dicatat pada histori pendidikan institusi ini.</small>
    @error('education_detail')<div class="invalid-feedback" id="education_detail-error" role="alert">{{ $message }}</div>@enderror
</div>
@endif
