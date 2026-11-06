export function clientOptions(source, emptyOption = null) {
    const rows = source || [];
    const ids = new Set(rows.map((row) => row.id));
    const option = (row) => ({ value: row.id, label: row.name || row.email || row.title || row.id });
    const options = rows.filter((row) => !row.parent_client_id || !ids.has(row.parent_client_id))
        .map((row) => ({
            ...option(row),
            children: rows.filter((child) => child.parent_client_id === row.id).map(option),
        }));
    return emptyOption ? [emptyOption, ...options] : options;
}
