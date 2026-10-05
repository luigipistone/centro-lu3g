export function daysBetween(start, end) {
    const [startYear, startMonth, startDay] = start.split('-').map(Number);
    const [endYear, endMonth, endDay] = end.split('-').map(Number);
    return (Date.UTC(endYear, endMonth - 1, endDay) - Date.UTC(startYear, startMonth - 1, startDay)) / 86400000;
}

export function addDays(date, days) {
    const [year, month, day] = date.split('-').map(Number);
    const next = new Date(Date.UTC(year, month - 1, day + days));
    return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}-${String(next.getUTCDate()).padStart(2, '0')}`;
}

export function movedTaskDates(task, date) {
    const start = task.start_date && task.start_date <= task.due_date ? task.start_date : task.due_date;
    const duration = daysBetween(start, task.due_date);

    return {
        start_date: duration > 0 ? date : null,
        due_date: duration > 0 ? addDays(date, duration) : date,
    };
}
