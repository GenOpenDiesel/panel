import { ClonePlugin } from '@/api/server/cloneCleanup';

const isSafePattern = (pattern: string): boolean =>
    pattern !== '' && !pattern.includes('/') && !pattern.includes('\\') && !pattern.includes('..');

export const parsePluginPatterns = (input: string): string[] =>
    input
        .split(',')
        .map((pattern) => pattern.trim())
        .filter((pattern) => isSafePattern(pattern));

const matchesPattern = (fileName: string, pattern: string): boolean => {
    const prefix = pattern.replace(/\*+$/, '');
    if (!prefix) {
        return false;
    }

    return fileName.toLowerCase().startsWith(prefix.toLowerCase());
};

export const matchPluginPatterns = (plugins: ClonePlugin[], input: string): string[] => {
    const patterns = parsePluginPatterns(input);
    if (patterns.length === 0) {
        return [];
    }

    const matched = plugins
        .filter((plugin) => plugin.type === 'file')
        .filter((plugin) => patterns.some((pattern) => matchesPattern(plugin.name, pattern)))
        .map((plugin) => plugin.name);

    return Array.from(new Set(matched)).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
};
