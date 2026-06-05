@extends('layouts.admin')

@section('title')
    Superadmin
@endsection

@section('content-header')
    <h1>Superadmin<small>Narzędzia operatorskie dostępne tylko dla root admina.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Superadmin</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="{{ $activeTab === 'backups' ? 'active' : '' }}">
                    <a href="{{ route('admin.superadmin', ['tab' => 'backups']) }}">Log backupów</a>
                </li>
                <li class="{{ $activeTab === 'logs' ? 'active' : '' }}">
                    <a href="{{ route('admin.superadmin', ['tab' => 'logs']) }}">Analiza logów</a>
                </li>
            </ul>
            <div class="tab-content">
                @if ($activeTab === 'logs')
                    <div class="tab-pane active" id="tab-logs">
                        @include('admin.superadmin.partials.log-analysis')
                    </div>
                @else
                    <div class="tab-pane active" id="tab-backups">
                        @include('admin.superadmin.partials.backup-log')
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    @stack('superadmin-scripts')
@endsection
