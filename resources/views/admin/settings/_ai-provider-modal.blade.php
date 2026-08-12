{{--
    Shared markup for both the "Add Provider" modal and each custom
    provider's own edit modal. Built-ins never get an edit modal at all
    (see edit.blade.php's @foreach ...->reject->isBuiltIn()).
--}}
<dialog id="{{ $modalId }}" aria-labelledby="{{ $modalId }}-title" class="rounded-2xl p-0 pm-dialog-lg shadow-2xl backdrop:bg-slate-900/50">
    <form method="POST" action="{{ $action }}" class="p-6 space-y-4">
        @csrf
        @if ($method)
            @method($method)
        @endif

        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 id="{{ $modalId }}-title" class="text-lg font-bold text-slate-800">{{ $provider->exists ? 'Edit' : 'Add' }} AI Provider</h2>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div>
            <label for="name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
            <input type="text" id="name-{{ $modalId }}" name="name" value="{{ old('name', $provider->name) }}"
                   placeholder="e.g. Groq, Mistral" required aria-required="true" class="pm-input">
        </div>

        <div>
            <label for="key-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Key</label>
            <input type="text" id="key-{{ $modalId }}" name="key" value="{{ old('key', $provider->key) }}"
                   placeholder="e.g. groq" required aria-required="true" class="pm-input font-mono text-sm"
                   {{ $provider->exists ? 'readonly' : '' }}>
            <p class="text-xs text-slate-400 mt-1">Letters, numbers, dashes, underscores only. Can't be changed once set.</p>
        </div>

        <div>
            <label for="api_base_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">API Base URL</label>
            <input type="url" id="api_base_url-{{ $modalId }}" name="api_base_url" value="{{ old('api_base_url', $provider->api_base_url) }}"
                   placeholder="https://api.groq.com/openai/v1/chat/completions" required aria-required="true" class="pm-input">
            <p class="text-xs text-slate-400 mt-1">The full chat-completions endpoint URL, OpenAI-compatible request/response shape.</p>
        </div>

        <div>
            <label for="default_model-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Model Name</label>
            <input type="text" id="default_model-{{ $modalId }}" name="default_model" value="{{ old('default_model', $provider->default_model) }}"
                   placeholder="e.g. llama-3.1-70b-versatile" required aria-required="true" class="pm-input">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_enabled-{{ $modalId }}" name="is_enabled" value="1"
                   @checked(old('is_enabled', $provider->exists ? $provider->is_enabled : true))
                   class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
            <label for="is_enabled-{{ $modalId }}" class="text-sm text-slate-700">Enabled (selectable by users)</label>
        </div>

        @error('provider')
            <p role="alert" class="text-sm text-rose-600">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-3 pt-2 border-t border-slate-100 mt-2">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save
            </button>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                Cancel
            </button>
        </div>
    </form>
</dialog>
