{{--
    Shared field renderer used by:
      - crud/form.blade.php (the full-page create/edit fallback — still
        reachable by direct URL even though the index page now defaults
        to a modal)
      - crud/index.blade.php's create/edit <dialog> (the default UX)

    Expects: $fields (field config array) and $item (an Eloquent model
    instance, or a fresh `new $model` for "create"). Uses old()-then-item
    fallback so validation-error redisplay works in both contexts. A field
    can set 'default' (e.g. true for a checkbox) used only when there's no
    old() input AND no $item value — i.e. a brand new "create" form.
--}}
@foreach ($fields as $field)
    @php
        $name = $field['name'];
        $fieldId = 'field-' . $name;
        $errorId = $fieldId . '-error';
        $rawDefault = data_get($item, $name, $field['default'] ?? '');
        $old = old($name, $rawDefault);
        if (is_object($old) && method_exists($old, 'format')) {
            $old = $field['type'] === 'datetime-local' ? $old->format('Y-m-d\TH:i') : $old->format('Y-m-d');
        }
        $hasError = $errors->has($name);
        $isRequired = !empty($field['required']);
        $inputClasses = 'pm-input' . ($hasError ? ' border-rose-400' : '');
        $hintId = $fieldId . '-hint';
        $describedBy = trim((!empty($field['hint']) ? $hintId . ' ' : '') . ($hasError ? $errorId : ''));
    @endphp
    <div>
        @if ($field['type'] !== 'checkbox')
            <label for="{{ $fieldId }}" class="block text-sm font-medium text-slate-700 mb-1">
                {{ $field['label'] }}
                @if ($isRequired)
                    <span class="text-rose-500" aria-hidden="true">*</span>
                    <span class="sr-only">(required)</span>
                @endif
                @if ($field['money'] ?? false)
                    <span class="text-slate-400 font-normal">({{ $siteSettings->default_currency_code ?? 'UGX' }})</span>
                @endif
            </label>
        @endif

        @if ($field['type'] === 'checkbox')
            <div class="flex items-center gap-2">
                {{-- Hidden fallback ensures an unchecked box still submits
                     "0" rather than omitting the field entirely. --}}
                <input type="hidden" name="{{ $name }}" value="0">
                <input
                    type="checkbox"
                    id="{{ $fieldId }}"
                    name="{{ $name }}"
                    value="1"
                    @checked((bool) $old)
                    class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]"
                >
                <label for="{{ $fieldId }}" class="text-sm text-slate-700">{{ $field['label'] }}</label>
            </div>
        @elseif ($field['type'] === 'textarea')
            <div class="flex items-start gap-2">
                <textarea
                    id="{{ $fieldId }}"
                    name="{{ $name }}"
                    rows="3"
                    @if (!empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                    @if ($isRequired) required aria-required="true" @endif
                    @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                    class="flex-1 {{ $inputClasses }}"
                >{{ $old }}</textarea>
                <button type="button"
                        class="pm-voice-input-btn hidden mt-1 w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[var(--brand-1)] hover:bg-[var(--brand-1-tint-10)] transition-colors"
                        onclick="pmStartDictation('{{ $fieldId }}', this)"
                        aria-label="Dictate {{ $field['label'] }} using voice"
                        aria-pressed="false"
                        title="Voice input">
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                </button>
            </div>
        @elseif ($field['type'] === 'select')
            <select
                id="{{ $fieldId }}"
                name="{{ $name }}"
                @if ($isRequired) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="{{ $inputClasses }}"
            >
                <option value="">-- Select --</option>
                @foreach (($field['options'] ?? []) as $value => $label)
                    <option value="{{ $value }}" @selected((string) $old === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($field['type'] === 'text')
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    id="{{ $fieldId }}"
                    name="{{ $name }}"
                    value="{{ $old }}"
                    @if (!empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                    @if ($isRequired) required aria-required="true" @endif
                    @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                    class="flex-1 {{ $inputClasses }}"
                >
                <button type="button"
                        class="pm-voice-input-btn hidden w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[var(--brand-1)] hover:bg-[var(--brand-1-tint-10)] transition-colors"
                        onclick="pmStartDictation('{{ $fieldId }}', this)"
                        aria-label="Dictate {{ $field['label'] }} using voice"
                        aria-pressed="false"
                        title="Voice input">
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                </button>
            </div>
        @else
            <input
                type="{{ $field['type'] }}"
                id="{{ $fieldId }}"
                name="{{ $name }}"
                value="{{ $old }}"
                @if (!empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                @if ($field['type'] === 'number') step="0.01" @endif
                @if ($isRequired) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="{{ $inputClasses }}"
            >
        @endif

        @if (!empty($field['hint']))
            <p id="{{ $hintId }}" class="text-xs text-slate-400 mt-1">{{ $field['hint'] }}</p>
        @endif

        @error($name)
            <p id="{{ $errorId }}" role="alert" class="text-sm text-rose-600 mt-1 flex items-center gap-1">
                <i class="fa-solid fa-circle-exclamation text-xs" aria-hidden="true"></i>
                {{ $message }}
            </p>
        @enderror
    </div>
@endforeach
