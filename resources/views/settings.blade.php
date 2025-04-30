@extends('statamic::layout')

@section('title', 'AI Translator Instellingen')

@section('content')
    <h1>AI Translator Instellingen</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('statamic.cp.ai-translator.pagetranslator.settings.save') }}">
        @csrf

        <fieldset>
            <legend>API Instellingen</legend>

            <div class="form-group">
                <label for="api_key">API Key</label>
                <input type="text" name="api_key" id="api_key" class="form-control" value="">
            </div>

            <button type="submit" class="btn btn-primary">Opslaan</button>
        </fieldset>
    </form>
@endsection
