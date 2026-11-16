import test from 'node:test';
import assert from 'node:assert/strict';

import { buildScheduleLoads } from './scheduleLoad.js';

test('counts distinct teaching and overall slots with per-day breakdowns', () => {
    const teacher = 1;
    const proctor = 2;
    const idle = 3;

    const schedules = [
        // Two sections sit the same subject at the same time: one teaching
        // slot and one overall slot, but two proctor assignments.
        { day: 1, subject_teacher_id: teacher, proctor_id: proctor, start_time: '08:00:00', end_time: '09:00:00' },
        { day: 1, subject_teacher_id: teacher, proctor_id: proctor, start_time: '08:00:00', end_time: '09:00:00' },
        // A self-proctored elective adds the same slot to teaching and
        // proctor; it must only count once toward the overall total.
        { day: 2, subject_teacher_id: teacher, proctor_id: teacher, start_time: '10:00:00', end_time: '11:00:00' },
    ];

    const loads = buildScheduleLoads(schedules, [teacher, proctor, idle]);

    assert.equal(loads.teaching[teacher], 2);
    assert.equal(loads.proctor[teacher], 1);
    assert.equal(loads.overall[teacher], 2);

    assert.equal(loads.teaching[proctor], 0);
    assert.equal(loads.proctor[proctor], 2);
    assert.equal(loads.overall[proctor], 1);

    assert.deepEqual(loads.teachingByDay[teacher], { 1: 1, 2: 1 });
    assert.deepEqual(loads.proctorByDay[teacher], { 2: 1 });
    assert.deepEqual(loads.overallByDay[teacher], { 1: 1, 2: 1 });

    assert.deepEqual(loads.proctorByDay[proctor], { 1: 2 });
    assert.deepEqual(loads.overallByDay[proctor], { 1: 1 });

    assert.equal(loads.teaching[idle], 0);
    assert.equal(loads.proctor[idle], 0);
    assert.equal(loads.overall[idle], 0);
    assert.deepEqual(loads.teachingByDay[idle], {});
});

test('time strings with and without seconds normalize to the same slot', () => {
    const teacher = 1;

    const loads = buildScheduleLoads(
        [
            { day: 2, subject_teacher_id: teacher, start_time: '08:00', end_time: '09:30' },
            { day: 2, subject_teacher_id: teacher, start_time: '08:00:00', end_time: '09:30:00' },
        ],
        [teacher],
    );

    assert.equal(loads.teaching[teacher], 1);
    assert.equal(loads.overall[teacher], 1);
});

test('day defaults to 1 and teachers outside the provided list are still counted', () => {
    const known = 1;
    const deleted = 9;

    const loads = buildScheduleLoads(
        [{ day: null, subject_teacher_id: deleted, proctor_id: deleted, start_time: '07:00:00', end_time: '08:00:00' }],
        [known],
    );

    assert.equal(loads.teaching[known], 0);
    assert.equal(loads.teaching[deleted], 1);
    assert.equal(loads.proctor[deleted], 1);
    assert.equal(loads.overall[deleted], 1);
    assert.deepEqual(loads.overallByDay[deleted], { 1: 1 });
});
