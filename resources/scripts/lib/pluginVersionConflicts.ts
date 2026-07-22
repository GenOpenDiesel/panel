const PLUGIN_COPY_SUFFIX_REGEX = /\s+\(\d+\)$/;
const PLUGIN_VERSION_SUFFIX_REGEX = /^(.*?)(?:[-_\s.]v?)(\d+(?:[._-]\d+)*(?:[-_+][a-z0-9]+)?)$/i;

interface PluginFile {
    name: string;
    isFile: boolean;
}

export type PluginVersionConflict = {
    name: string;
    files: string[];
};

const getPluginVersionParts = (file: PluginFile): { key: string; name: string } | null => {
    if (!file.isFile || !file.name.toLowerCase().endsWith('.jar')) {
        return null;
    }

    const fileNameWithoutExtension = file.name
        .replace(/\.jar$/i, '')
        .replace(PLUGIN_COPY_SUFFIX_REGEX, '')
        .trim();
    const match = fileNameWithoutExtension.match(PLUGIN_VERSION_SUFFIX_REGEX);

    // When the name carries a version suffix (e.g. "Plugin-1.2"), group by the
    // versionless base name so different versions collide. Otherwise fall back to
    // the whole base name, so plain copies like "ProtocolLib (4)" / "ProtocolLib (18)"
    // still group together instead of being dropped.
    const name = match ? match[1].replace(/[-_\s.]+$/, '').trim() : fileNameWithoutExtension;

    if (!name) {
        return null;
    }

    return {
        key: name.toLowerCase(),
        name,
    };
};

export const findPluginVersionConflicts = (files: PluginFile[]): PluginVersionConflict[] => {
    const groups = new Map<string, { name: string; files: string[] }>();

    files.forEach((file) => {
        const plugin = getPluginVersionParts(file);

        if (!plugin) {
            return;
        }

        const group = groups.get(plugin.key) || { name: plugin.name, files: [] };

        group.files.push(file.name);
        groups.set(plugin.key, group);
    });

    return Array.from(groups.values())
        .filter((group) => group.files.length > 1)
        .map((group) => ({ name: group.name, files: group.files }));
};
