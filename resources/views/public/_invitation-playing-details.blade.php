<div class="row g-2 mb-3">
    @if($invitationContext['is_roster'])
    <div class="col-sm-6">
        <label class="form-label" for="jersey_number">Nomor Punggung</label>
        <input class="form-control @error('jersey_number') is-invalid @enderror" id="jersey_number" type="number" name="jersey_number" min="0" max="99" value="{{ old('jersey_number') }}" placeholder="Contoh: 10" aria-describedby="jersey_number-help jersey_number-error">
        <small id="jersey_number-help" class="form-text">Isi untuk tim ini; tidak diambil dari tim sebelumnya.</small>
        @error('jersey_number')<div class="invalid-feedback" id="jersey_number-error">{{ $message }}</div>@enderror
    </div>
    @endif
    <div class="col-sm-6">
        <label class="form-label" for="position">Posisi Bermain</label>
        @if($invitationContext['positions'] !== [])
        <select class="form-select @error('position') is-invalid @enderror" id="position" name="position" aria-describedby="position-error">
            <option value="">Pilih posisi untuk tim ini</option>
            @foreach($invitationContext['positions'] as $position)
            <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
            @endforeach
        </select>
        @else
        <input class="form-control @error('position') is-invalid @enderror" id="position" type="text" name="position" maxlength="50" value="{{ old('position') }}" aria-describedby="position-error">
        @endif
        @error('position')<div class="invalid-feedback" id="position-error">{{ $message }}</div>@enderror
    </div>
</div>
