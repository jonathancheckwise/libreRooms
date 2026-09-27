 <div class="form-group" id="event-info-form-group">
    <h3 class="form-group-title">{{ __('General information') }}</h3>
    @php
        $currentType = old('event_type') ?? (($eventType ?? null) instanceof \App\Enums\ReservationType ? ($eventType ?? null)->value : ($eventType ?? null));
    @endphp
    <fieldset class="form-element">
        <div class="form-field">
            <label for="event_type" class="form-element-title">{{ __('Type of activity') }}</label>
            <select id="event_type" name="event_type" class="form-select">
                <option value="" @selected(! $currentType)>{{ __('— Select —') }}</option>
                @foreach(\App\Enums\ReservationType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('event_type')
                <span class="text-red-600 text-sm">{{ $message }}</span>
            @enderror
        </div>
    </fieldset>
    <fieldset class="form-element">
        <div class="form-field">
            <label for="res_title" class="form-element-title">{{ __('Event/activity name') }} *</label>
            <input
                type="text"
                id="res_title"
                name="res_title"
                required
                value="{{ old('res_title') ?? $title }}"
            >
            @error('res_title')
                <span class="text-red-600 text-sm">{{ $message }}</span>
            @enderror
        </div>
    </fieldset>
     <fieldset class="form-element">
         <div class="form-field">
             <label for="res_description" class="form-element-title">{{ __('Description and special requests') }}</label>
             <textarea
                 rows="5"
                 id="res_description"
                 name="res_description"
             >{{ old('res_description') ?? $description }}</textarea>
             @error('res_description')
                 <span class="text-red-600 text-sm">{{ $message }}</span>
             @enderror
         </div>
     </fieldset>
 </div>
