export const entryKinds = [
    {
        value: 'event',
        label: 'Event',
        description: 'A moment that meant something.',
    },
    {
        value: 'accomplishment',
        label: 'Accomplishment',
        description: 'Something you made happen.',
    },
    {
        value: 'achievement',
        label: 'Achievement',
        description: 'A milestone you worked toward.',
    },
] as const;

export type EntryKind = (typeof entryKinds)[number]['value'];

export type Entry = {
    id: number;
    title: string;
    kind: EntryKind;
    occurred_on: string;
    body: string;
    reflection: string | null;
};

export type EntryPage = {
    data: Entry[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export function formatEntryDate(
    value: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    return new Intl.DateTimeFormat(
        'en',
        options ?? {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        },
    ).format(new Date(`${value}T12:00:00`));
}
