import yaml from 'js-yaml';
import CodeMirror from 'codemirror';

export type StructuredFileType = 'json' | 'yaml';

export interface StructuredValidationIssue {
    line: number;
    column: number;
    message: string;
}

export const getStructuredFileType = (filename: string): StructuredFileType | null => {
    const name = filename.split('/').pop()?.toLowerCase() || '';

    if (name.endsWith('.json')) {
        return 'json';
    }

    if (name.endsWith('.yml') || name.endsWith('.yaml')) {
        return 'yaml';
    }

    return null;
};

export const getStructuredValidationSummary = (
    filename: string,
    issue?: StructuredValidationIssue | null
): string => {
    const type = getStructuredFileType(filename);

    if (!type) {
        return 'Plik zawiera błąd składni. Popraw go przed zapisem.';
    }

    const label = type === 'json' ? 'JSON' : 'YAML';
    const line = issue && issue.line >= 0 ? issue.line + 1 : null;

    if (line) {
        return `Plik zawiera błąd składni ${label} (linia ${line}). Popraw go przed zapisem.`;
    }

    return `Plik zawiera błąd składni ${label}. Popraw go przed zapisem.`;
};

export const validateStructuredFileContent = (
    filename: string,
    content: string
): StructuredValidationIssue | null => {
    const type = getStructuredFileType(filename);

    if (!type || content.trim() === '') {
        return null;
    }

    if (type === 'json') {
        try {
            JSON.parse(content);

            return null;
        } catch (error) {
            return {
                line: 0,
                column: 0,
                message: error instanceof Error ? error.message : 'Nieprawidłowy JSON.',
            };
        }
    }

    try {
        yaml.loadAll(content);

        return null;
    } catch (error) {
        const yamlError = error as { mark?: { line?: number; column?: number }; message?: string };

        return {
            line: yamlError.mark?.line ?? 0,
            column: yamlError.mark?.column ?? 0,
            message: yamlError.message || 'Nieprawidłowy YAML.',
        };
    }
};

export const getStructuredFileLintAnnotations = (
    filename: string | undefined,
    content: string
): CodeMirror.Annotation[] => {
    if (!filename) {
        return [];
    }

    const issue = validateStructuredFileContent(filename, content);

    if (!issue) {
        return [];
    }

    const from = CodeMirror.Pos(issue.line, issue.column);

    return [
        {
            from,
            to: from,
            message: getStructuredValidationSummary(filename, issue),
            severity: 'error',
        },
    ];
};
