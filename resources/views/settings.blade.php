@extends('statamic::layout')
@section('title', 'AI Translator settings')
@section('content')
@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
    <publish-form
            title="AI Translator settings"
            action="{{ cp_route('ai-translator.config.edit')}}"
            :blueprint='@json($blueprint)'
            :meta='@json($meta)'
            :values='@json($values)'
    ></publish-form>
@stop
