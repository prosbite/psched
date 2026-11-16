/**
 * Mirror of App\Services\ScheduleLoad::fromSchedules() for the schedule group
 * page, so the load summary always derives from the same schedules the grid
 * renders.
 *
 * A teaching/overall "busy slot" is a unique day/start/end combination, so a
 * teacher handling the same subject across several sections at the same time
 * is only counted once. Proctoring is an assignment count: every schedule row
 * with that proctor counts. A self-proctored schedule therefore counts once
 * toward the overall total.
 */

const normalizeTime = (time) => String(time ?? '').trim().slice(0, 5);

const slotKey = (day, start, end) => `${day}|${normalizeTime(start)}|${normalizeTime(end)}`;

const toTeacherId = (value) => (value === null || value === undefined ? null : Number(value));

const countByDay = (keys) => {
    const counts = {};

    for (const key of keys) {
        const day = Number(key.split('|', 1)[0]);
        counts[day] = (counts[day] ?? 0) + 1;
    }

    return counts;
};

/**
 * @param {Array<object>} schedules
 * @param {Array<number|string>} teacherIds
 * @returns {{
 *     teaching: Record<string, number>,
 *     proctor: Record<string, number>,
 *     overall: Record<string, number>,
 *     teachingByDay: Record<string, Record<string, number>>,
 *     proctorByDay: Record<string, Record<string, number>>,
 *     overallByDay: Record<string, Record<string, number>>,
 * }}
 */
export function buildScheduleLoads(schedules, teacherIds) {
    const teachingKeys = new Map();
    const overallKeys = new Map();
    const proctorCounts = new Map();
    const proctorByDay = new Map();

    const ensure = (id) => {
        if (! teachingKeys.has(id)) {
            teachingKeys.set(id, new Set());
        }

        if (! overallKeys.has(id)) {
            overallKeys.set(id, new Set());
        }

        if (! proctorCounts.has(id)) {
            proctorCounts.set(id, 0);
        }

        if (! proctorByDay.has(id)) {
            proctorByDay.set(id, new Map());
        }
    };

    for (const id of new Set((teacherIds ?? []).map(Number))) {
        ensure(id);
    }

    for (const schedule of schedules ?? []) {
        if (schedule === null || schedule === undefined) {
            continue;
        }

        if (schedule.start_time === null || schedule.start_time === undefined) {
            continue;
        }

        if (schedule.end_time === null || schedule.end_time === undefined) {
            continue;
        }

        const day = Number(schedule.day ?? 1);
        const key = slotKey(day, schedule.start_time, schedule.end_time);
        const subjectTeacher = toTeacherId(schedule.subject_teacher_id);
        const proctor = toTeacherId(schedule.proctor_id);

        if (subjectTeacher !== null) {
            ensure(subjectTeacher);
            teachingKeys.get(subjectTeacher).add(key);
            overallKeys.get(subjectTeacher).add(key);
        }

        if (proctor !== null) {
            ensure(proctor);
            overallKeys.get(proctor).add(key);
            proctorCounts.set(proctor, proctorCounts.get(proctor) + 1);

            const byDay = proctorByDay.get(proctor);
            byDay.set(day, (byDay.get(day) ?? 0) + 1);
        }
    }

    const teaching = {};
    const proctor = {};
    const overall = {};
    const teachingByDay = {};
    const overallByDay = {};
    const proctorByDayCounts = {};

    for (const id of teachingKeys.keys()) {
        teaching[id] = teachingKeys.get(id).size;
        overall[id] = overallKeys.get(id).size;
        proctor[id] = proctorCounts.get(id) ?? 0;
        teachingByDay[id] = countByDay(teachingKeys.get(id));
        overallByDay[id] = countByDay(overallKeys.get(id));
        proctorByDayCounts[id] = Object.fromEntries(proctorByDay.get(id));
    }

    return {
        teaching,
        proctor,
        overall,
        teachingByDay,
        proctorByDay: proctorByDayCounts,
        overallByDay,
    };
}
