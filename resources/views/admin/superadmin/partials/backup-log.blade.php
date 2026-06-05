<div class="box box-primary" style="border-top: 0; margin-bottom: 0;">
    <div class="box-header with-border">
        <h3 class="box-title">Centralny log backupów</h3>
        <div class="box-tools">
            <span class="label label-default" id="backup-log-total">Ładowanie...</span>
        </div>
    </div>
    <div class="box-body table-responsive no-padding">
        <table class="table table-hover" id="backup-activity-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Akcja</th>
                    <th>Backup</th>
                    <th>Serwer</th>
                    <th>Użytkownik</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody id="backup-activity-body">
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        <i class="fa fa-refresh fa-spin"></i> Pobieranie logów...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="box-footer clearfix">
        <div class="pull-left">
            <small class="text-muted">
                Wyświetlane są wyłącznie akcje wykonane przez użytkowników (bez wpisów systemowych).
                Alerty Discord wysyłane są automatycznie po każdej akcji użytkownika.
            </small>
        </div>
        <ul class="pagination pagination-sm no-margin pull-right" id="backup-activity-pagination"></ul>
    </div>
</div>

@push('superadmin-scripts')
    <script>
    (function () {
        var currentPage = 1;
        var logsUrl = '{{ route('admin.superadmin.backup-logs') }}';

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatDate(iso) {
            if (!iso) return '-';
            var date = new Date(iso);
            if (isNaN(date.getTime())) return iso;
            return date.toLocaleString();
        }

        function eventLabelClass(label) {
            if (label === 'Pobranie') return 'label-info';
            if (label === 'Utworzenie') return 'label-warning';
            if (label === 'Wgranie') return 'label-success';
            return 'label-default';
        }

        function renderRows(entries) {
            var body = document.getElementById('backup-activity-body');
            if (!entries.length) {
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Brak wpisów w logu.</td></tr>';
                return;
            }

            body.innerHTML = entries.map(function (entry) {
                var serverCell = entry.server_id
                    ? '<a href="/admin/servers/view/' + entry.server_id + '">' + escapeHtml(entry.server_name || ('#' + entry.server_id)) + '</a>'
                    : '<span class="text-muted">-</span>';

                var actorName = entry.actor && entry.actor.name ? entry.actor.name : 'System';
                var actorEmail = entry.actor && entry.actor.email ? '<br><small class="text-muted">' + escapeHtml(entry.actor.email) + '</small>' : '';

                return '<tr>' +
                    '<td>' + escapeHtml(formatDate(entry.timestamp)) + '</td>' +
                    '<td><span class="label ' + eventLabelClass(entry.event_label) + '">' + escapeHtml(entry.event_label) + '</span></td>' +
                    '<td>' + escapeHtml(entry.backup_name || '-') + '</td>' +
                    '<td>' + serverCell + '</td>' +
                    '<td>' + escapeHtml(actorName) + actorEmail + '</td>' +
                    '<td><code>' + escapeHtml(entry.ip || '-') + '</code></td>' +
                    '</tr>';
            }).join('');
        }

        function renderPagination(meta) {
            var pagination = document.getElementById('backup-activity-pagination');
            if (!meta || meta.last_page <= 1) {
                pagination.innerHTML = '';
                return;
            }

            var items = [];
            if (meta.current_page > 1) {
                items.push('<li><a href="#" data-page="' + (meta.current_page - 1) + '">&laquo;</a></li>');
            }

            for (var page = 1; page <= meta.last_page; page++) {
                if (page === 1 || page === meta.last_page || Math.abs(page - meta.current_page) <= 2) {
                    var active = page === meta.current_page ? ' class="active"' : '';
                    items.push('<li' + active + '><a href="#" data-page="' + page + '">' + page + '</a></li>');
                } else if (Math.abs(page - meta.current_page) === 3) {
                    items.push('<li class="disabled"><span>...</span></li>');
                }
            }

            if (meta.current_page < meta.last_page) {
                items.push('<li><a href="#" data-page="' + (meta.current_page + 1) + '">&raquo;</a></li>');
            }

            pagination.innerHTML = items.join('');
        }

        function loadLogs(page) {
            currentPage = page || 1;
            var body = document.getElementById('backup-activity-body');
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><i class="fa fa-refresh fa-spin"></i> Pobieranie logów...</td></tr>';

            fetch(logsUrl + '?page=' + currentPage, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (payload) {
                    renderRows(payload.data || []);
                    renderPagination(payload.meta || {});
                    document.getElementById('backup-log-total').textContent = 'Łącznie: ' + ((payload.meta && payload.meta.total) || 0);
                })
                .catch(function () {
                    body.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Nie udało się pobrać logów backupów.</td></tr>';
                    document.getElementById('backup-log-total').textContent = 'Błąd';
                });
        }

        document.getElementById('backup-activity-pagination').addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.dataset || !target.dataset.page) {
                return;
            }

            event.preventDefault();
            loadLogs(parseInt(target.dataset.page, 10));
        });

        loadLogs(1);
    })();
    </script>
@endpush
