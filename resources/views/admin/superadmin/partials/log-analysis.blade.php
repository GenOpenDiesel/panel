<div class="box box-primary" style="border-top: 0; margin-bottom: 0;">
    <div class="box-header with-border">
        <h3 class="box-title">Analiza latest.log</h3>
        <div class="box-tools">
            <span class="label label-default" id="log-analysis-summary">Gotowe do skanowania</span>
        </div>
    </div>
    <div class="box-body">
        <div class="form-group">
            <label for="log_analysis_phrases" class="control-label">Frazy do wyszukania</label>
            <textarea
                id="log_analysis_phrases"
                class="form-control"
                rows="4"
                placeholder="OutOfMemoryError, Could not pass event, Exception"
            ></textarea>
            <p class="text-muted" style="margin-top: 10px;">
                Wpisz frazy po przecinku lub w osobnych liniach. Panel przeskanuje wszystkie zainstalowane serwery
                i przeszuka ostatnie 4&nbsp;MiB pliku <code>logs/latest.log</code> na każdym z nich.
            </p>
        </div>
        <div class="form-group">
            <label class="control-label">Popularne błędy</label>
            <div id="log-analysis-presets" style="display: flex; flex-wrap: wrap; gap: 8px;">
                @foreach (config('pterodactyl.log_analysis.popular_errors', []) as $error)
                    <button
                        type="button"
                        class="btn btn-default btn-sm log-analysis-preset"
                        data-phrase="{{ $error }}"
                    >{{ $error }}</button>
                @endforeach
            </div>
            <p class="text-muted" style="margin-top: 10px; margin-bottom: 0;">
                Kliknij błąd, aby od razu przeskanować wszystkie serwery.
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="log-analysis-submit">
            <i class="fa fa-search"></i> Analizuj
        </button>
    </div>
    <div class="box-body table-responsive no-padding" id="log-analysis-results-wrap" style="display: none;">
        <table class="table table-hover" id="log-analysis-table">
            <thead>
                <tr>
                    <th>Serwer</th>
                    <th>Status</th>
                    <th>Fraza</th>
                    <th>Linia z logu</th>
                </tr>
            </thead>
            <tbody id="log-analysis-body"></tbody>
        </table>
    </div>
</div>

@push('superadmin-scripts')
    <script>
    (function () {
        var analyzeUrl = '{{ route('admin.superadmin.analyze-logs') }}';
        var csrfToken = '{{ csrf_token() }}';
        var submitButton = document.getElementById('log-analysis-submit');
        var phrasesInput = document.getElementById('log_analysis_phrases');
        var summary = document.getElementById('log-analysis-summary');
        var resultsWrap = document.getElementById('log-analysis-results-wrap');
        var body = document.getElementById('log-analysis-body');

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function statusClass(label) {
            if (label === 'znaleziono') return 'label-success';
            if (label === 'brak pliku') return 'label-default';
            if (label === 'błąd') return 'label-danger';
            return 'label-default';
        }

        function renderRows(results) {
            if (!results.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nie znaleziono dopasowań ani błędów.</td></tr>';
                return;
            }

            body.innerHTML = results.map(function (entry) {
                var serverCell = entry.server_id
                    ? '<a href="/admin/servers/view/' + entry.server_id + '">' + escapeHtml(entry.server_name || ('#' + entry.server_id)) + '</a>'
                    : escapeHtml(entry.server_name || '-');

                if (entry.node_name) {
                    serverCell += '<br><small class="text-muted">' + escapeHtml(entry.node_name) + '</small>';
                }

                var phraseCell = entry.phrase ? '<code>' + escapeHtml(entry.phrase) + '</code>' : '<span class="text-muted">—</span>';
                var lineCell = entry.line
                    ? '<code style="white-space: pre-wrap; word-break: break-word;">' + escapeHtml(entry.line) + '</code>'
                    : '<span class="text-muted">' + escapeHtml(entry.message || '—') + '</span>';

                return '<tr>' +
                    '<td>' + serverCell + '</td>' +
                    '<td><span class="label ' + statusClass(entry.status_label) + '">' + escapeHtml(entry.status_label) + '</span></td>' +
                    '<td>' + phraseCell + '</td>' +
                    '<td>' + lineCell + '</td>' +
                    '</tr>';
            }).join('');
        }

        function setPresetActive(phrase) {
            document.querySelectorAll('.log-analysis-preset').forEach(function (button) {
                button.classList.toggle('btn-primary', button.dataset.phrase === phrase);
                button.classList.toggle('btn-default', button.dataset.phrase !== phrase);
            });
        }

        function runAnalysis(phrases) {
            if (!phrases) {
                summary.textContent = 'Podaj frazy do wyszukania';
                summary.className = 'label label-warning';
                return;
            }

            submitButton.disabled = true;
            document.querySelectorAll('.log-analysis-preset').forEach(function (button) {
                button.disabled = true;
            });
            summary.textContent = 'Skanowanie serwerów...';
            summary.className = 'label label-warning';
            resultsWrap.style.display = 'block';
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted"><i class="fa fa-refresh fa-spin"></i> Analizowanie latest.log na serwerach...</td></tr>';

            fetch(analyzeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ phrases: phrases }),
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.error || ('HTTP ' + response.status));
                        }
                        return payload;
                    });
                })
                .then(function (payload) {
                    renderRows(payload.results || []);
                    var meta = payload.meta || {};
                    summary.textContent = 'Serwery: ' + (meta.servers_scanned || 0)
                        + ' | Dopasowania: ' + (meta.matches || 0)
                        + ' | Błędy: ' + (meta.errors || 0)
                        + ' | Brak pliku: ' + (meta.missing_file || 0);
                    summary.className = 'label label-success';
                })
                .catch(function (error) {
                    body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">' + escapeHtml(error.message || 'Nie udało się przeanalizować logów.') + '</td></tr>';
                    summary.textContent = 'Błąd analizy';
                    summary.className = 'label label-danger';
                })
                .then(function () {
                    submitButton.disabled = false;
                    document.querySelectorAll('.log-analysis-preset').forEach(function (button) {
                        button.disabled = false;
                    });
                });
        }

        submitButton.addEventListener('click', function () {
            setPresetActive('');
            runAnalysis(phrasesInput.value.trim());
        });

        document.querySelectorAll('.log-analysis-preset').forEach(function (button) {
            button.addEventListener('click', function () {
                var phrase = button.dataset.phrase || '';
                phrasesInput.value = phrase;
                setPresetActive(phrase);
                runAnalysis(phrase);
            });
        });
    })();
    </script>
@endpush
