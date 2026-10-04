@extends('layouts.app')

@section('title_full', 'Custom App'.' - '.$mailbox->name)

@section('main_class', 'fruit-ui')

@section('sidebar')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')
    <div class="page-content">
        @include('partials/flash_messages')

        @include('customapp::settings')
    </div>
@endsection
