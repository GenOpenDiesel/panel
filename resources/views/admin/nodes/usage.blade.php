@extends('layouts.admin')

@section('title')
    Zużycie węzłów
@endsection

@section('content-header')
    <h1>Zużycie węzłów<small>Bieżące zużycie zasobów na każdym węźle.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Zużycie węzłów</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Monitor zasobów</h3>
                <div class="box-tools">
                    <span class="label label-default" id="usage-updated-at">Ładowanie...</span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover" id="node-usage-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Węzeł</th>
                            <th>RAM (live)</th>
                            <th>CPU (live)</th>
                            <th>Dysk (live)</th>
                            <th>Alokacja RAM</th>
                            <th>Alokacja dysk</th>
                            <th class="text-center">Serwery</th>
                        </tr>
                    </thead>
                    <tbody id="node-usage-body">
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                <i class="fa fa-refresh fa-spin"></i> Pobieranie danych z węzłów...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <small class="text-muted">Dane odświeżane co 15 sekund. Odpowiedzi Wings są cache'owane po stronie panelu, aby nie obciążać węzłów.</small>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    (function () {
        var refreshInterval = 15000;

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
            var i = 0;
            var value = bytes;
            while (value >= 1024 && i < units.length - 1) {
                value /= 1024;
                i++;
            }
            return value.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        }

        function formatMib(mib) {
            if (!mib || mib <= 0) return '0 MiB';
            if (mib >= 1024) return (mib / 1024).toFixed(1) + ' GiB';
            return mib.toLocaleString() + ' MiB';
        }

        function cpuProgressBar(absolute, label) {
            var barPercent = Math.min(100, Math.max(0, absolute));
            var css = barPercent <= 60 ? 'progress-bar-success' : (barPercent <= 85 ? 'progress-bar-warning' : 'progress-bar-danger');
            return '<div style="min-width:140px">' +
                '<small>' + label + '</small>' +
                '<div class="progress progress-xs" style="margin-bottom:0">' +
                '<div class="progress-bar ' + css + '" style="width:' + barPercent.toFixed(1) + '%"></div>' +
                '</div>' +
                '<small class="text-muted">' + absolute.toFixed(1) + '%</small>' +
                '</div>';
        }

        function progressBar(percent, label) {
            var css = percent <= 60 ? 'progress-bar-success' : (percent <= 85 ? 'progress-bar-warning' : 'progress-bar-danger');
            var safePercent = Math.min(100, Math.max(0, percent));
            return '<div style="min-width:140px">' +
                '<small>' + label + '</small>' +
                '<div class="progress progress-xs" style="margin-bottom:0">' +
                '<div class="progress-bar ' + css + '" style="width:' + safePercent.toFixed(1) + '%"></div>' +
                '</div>' +
                '<small class="text-muted">' + safePercent.toFixed(1) + '%</small>' +
                '</div>';
        }

        function statusIcon(online) {
            if (online) {
                return '<i class="fa fa-heartbeat text-success" title="Online"></i>';
            }
            return '<i class="fa fa-heart-o text-danger" title="Offline"></i>';
        }

        function renderNodes(data) {
            var $body = $('#node-usage-body');
            $body.empty();

            if (!data.nodes || !data.nodes.length) {
                $body.append('<tr><td colspan="8" class="text-center text-muted">Brak węzłów.</td></tr>');
                return;
            }

            data.nodes.forEach(function (node) {
                var maintenance = node.maintenance_mode ? '<span class="label label-warning"><i class="fa fa-wrench"></i></span> ' : '';
                var ramLabel = formatBytes(node.live.memory_bytes) + ' / ' + formatBytes(node.system.memory_bytes);
                var diskLabel = formatBytes(node.live.disk_bytes) + ' / ' + formatMib(node.allocated.disk_max_mib);
                var allocRamLabel = formatMib(node.allocated.memory_mib) + ' / ' + formatMib(node.allocated.memory_max_mib);
                var allocDiskLabel = formatMib(node.allocated.disk_mib) + ' / ' + formatMib(node.allocated.disk_max_mib);
                var cpuLabel = node.live.cpu_absolute.toFixed(1) + '% (' + (node.system.cpu_threads || 0) + ' wątków)';

                var row = '<tr>' +
                    '<td class="text-center">' + statusIcon(node.online) + '</td>' +
                    '<td>' + maintenance + '<a href="/admin/nodes/view/' + node.id + '">' + $('<div>').text(node.name).html() + '</a>' +
                    '<br><small class="text-muted">' + $('<div>').text(node.location).html() +
                    (node.wings_version ? ' · Wings ' + node.wings_version : '') + '</small></td>' +
                    '<td>' + (node.online ? progressBar(node.live.memory_percent, ramLabel) : '<span class="text-muted">—</span>') + '</td>' +
                    '<td>' + (node.online ? cpuProgressBar(node.live.cpu_absolute, cpuLabel) : '<span class="text-muted">—</span>') + '</td>' +
                    '<td>' + (node.online ? progressBar(node.live.disk_percent, diskLabel) : '<span class="text-muted">—</span>') + '</td>' +
                    '<td>' + progressBar(node.allocated.memory_percent, allocRamLabel) + '</td>' +
                    '<td>' + progressBar(node.allocated.disk_percent, allocDiskLabel) + '</td>' +
                    '<td class="text-center">' + node.live.running_servers + ' / ' + node.servers_count + '</td>' +
                    '</tr>';

                $body.append(row);
            });

            if (data.updated_at) {
                var date = new Date(data.updated_at);
                $('#usage-updated-at').text('Ostatnia aktualizacja: ' + date.toLocaleTimeString());
            }
        }

        var refreshTimer = null;

        function scheduleRefresh() {
            clearTimeout(refreshTimer);
            if (document.visibilityState === 'visible') {
                refreshTimer = setTimeout(loadUsage, refreshInterval);
            }
        }

        function loadUsage() {
            if (document.visibilityState !== 'visible') {
                return;
            }

            $.ajax({
                url: '{{ route('admin.nodes.usage.stats') }}',
                method: 'GET',
                timeout: 30000,
            }).done(function (data) {
                renderNodes(data);
                refreshInterval = (data.cached_seconds || 15) * 1000;
            }).fail(function () {
                $('#node-usage-body').html('<tr><td colspan="8" class="text-center text-danger">Nie udało się pobrać danych z węzłów.</td></tr>');
            }).always(function () {
                scheduleRefresh();
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                loadUsage();
            } else {
                clearTimeout(refreshTimer);
            }
        });

        loadUsage();
    })();
    </script>
@endsection
