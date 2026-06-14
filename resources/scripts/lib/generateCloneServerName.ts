export const extractCloneMode = (sourceName: string): string => {
    let name = sourceName.trim().replace(/^clone:\s*/i, '');
    name = name.replace(/\s+(prod(ukcja|iukcja)?|production)\s*$/i, '').trim();

    if (name === '') {
        return 'server';
    }

    const parts = name.split(/\s+/).filter(Boolean);

    return (parts.length > 1 ? parts[parts.length - 1] : parts[0]).toLowerCase();
};

export const generateCloneServerName = (sourceName: string, date = new Date()): string => {
    const mode = extractCloneMode(sourceName);
    const formattedDate = date.toLocaleDateString('pl-PL');

    return `clone ${mode} ${formattedDate}`;
};
