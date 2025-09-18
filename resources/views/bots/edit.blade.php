{{-- resources/views/bots/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Редактировать бота')

@section('content')
    @include('bots.create', ['bot' => $bot])
@endsection