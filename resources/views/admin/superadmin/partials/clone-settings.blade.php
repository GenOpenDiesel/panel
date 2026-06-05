<div class="box box-primary" style="border-top: 0; margin-bottom: 0;">
    <form method="POST" action="{{ route('admin.superadmin.clone-template') }}">
        {!! csrf_field() !!}
        <div class="box-header with-border">
            <h3 class="box-title">Szablon usuwania pluginów przy klonowaniu</h3>
        </div>
        <div class="box-body">
            <div class="form-group">
                <label for="plugin_template" class="control-label">Wzorce pluginów</label>
                <input
                    type="text"
                    class="form-control"
                    name="plugin_template"
                    id="plugin_template"
                    value="{{ old('plugin_template', $pluginTemplate) }}"
                    placeholder="luckperms*,goxy*,worldedit*"
                >
                <p class="text-muted" style="margin-top: 10px;">
                    Wpisz nazwy pluginów po przecinku. Gwiazdka na końcu jest opcjonalna —
                    <code>goxy*</code> i <code>goxy</code> działają tak samo.
                    Szablon jest automatycznie wczytywany w oknie czyszczenia pluginów po sklonowaniu serwera z backupu.
                    Usuwane są tylko pliki, foldery są pomijane.
                </p>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Zapisz szablon</button>
        </div>
    </form>
</div>
