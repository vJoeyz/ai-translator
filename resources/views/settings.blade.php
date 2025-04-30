@extends('statamic::layout')

@section('title', 'AI Translator Instellingen')

@section('content')
    <h1 class="mb-4">AI Translator Settings</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('statamic.cp.ai-translator.pagetranslator.settings.save') }}">
        @csrf

        <fieldset class="space-y-4 p-6 bg-white shadow-md rounded-lg">
            <div class="space-y-4">
                <div class="w-full">
                    <label for="api_key" class="block text-sm font-medium text-gray-700">Deepl API Key</label>
                    <input type="text" name="api_key" id="api_key" class="mt-1 block w-full p-3 text-black border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" value="{{ $api_key }}" required>
                </div>
                <div class="flex items-center justify-end">
                    <button type="submit" class="mt-3 px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 w-full sm:w-auto">
                        Save
                    </button>
                </div>
            </div>
        </fieldset>
        
    </form>
@endsection
