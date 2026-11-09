# Demo seeder: align all three assessment-day references

## Goal
Make `database/seeders/DemoDataSeeder.php` correspond to **all three** reference schedules the
user supplied, not just the first one. The current file (rewritten for reference #1 only) must be
extended so the demo dataset is the full union: every subject, section→room pairing, section-aware
subject teacher, and teacher that appears across the three images.

Context: the **locked** multi-day plan (`1791736445583-multi-day-exam-generator.md`) defines one
`ScheduleGroup` = the whole exam, `days = 3`, `exams_per_day = 3`, subjects chunked per grade by
`sort_order`, and per-grade time slots. The three images are the three exam days:
- **Day 1 = "B"**: Mathematics 1/Earth Science/Social Science 1 (G7) … grades 7–10 only.
- **Day 2 = "A"**: Filipino 1/Computer Science 1 (G7) … grades 7–12.
- **Day 3 = "#1"**: English 1/Integrated Science (G7) … grades 7–12 (the image from the previous turn).

Evidence this is the intended order: the *original* seeder's `SUBJECTS` lists are exactly
`[B subjects][A subjects][#1 subjects]` per grade (e.g. G9 = Social Science 3, Statistics 1,
Chemistry 2 │ Mathematics 3, Filipino 3, Computer Science 3 │ Biology 2, English 3, Physics 2).

## Approach
Keep the single-source derivation introduced in the rewrite, but carry a `day` tag and include all
three references:

- `REFERENCE` constant: one row per section sitting one assessment:
  `[day, grade, subject, start, end, section, room, proctor, teacher]`, in day order (1=B, 2=A, 3=#1).
- Derive from it: grade levels, sections + their rooms, subjects (first-appearance order = the
  per-grade `sort_order`), the full teacher roster (union of every `teacher`/`proctor` name), and
  `section_subject_teacher` rows.
- Mark elective subjects `is_elective` (see below).

## Per-grade subject order (final, day-tagged)
Used for the group pivot `sort_order` and to build the union.
- **G7**: Mathematics 1, Earth Science, Social Science 1 | Filipino 1, Computer Science 1 | English 1, Integrated Science
- **G8**: English 2, Mathematics 2B, Social Science 2 | Earth Science, Filipino 2, Mathematics 2A | Biology 1, Physics 1, Chemistry 1
- **G9**: Social Science 3, Statistics 1, Chemistry 2 | Mathematics 3, Filipino 3, Computer Science 3 | Biology 2, English 3, Physics 2
- **G10**: Chemistry 2, Social Science 4, Elective | Filipino 4, Mathematics 4 | English 4, Biology 2, Physics 2
- **G11**: Mathematics 5, Social Science 5, Elective | Physics 3 Level 2 (Science Core), Biology 3 Level 2 (Science Core), Filipino 5
- **G12**: Social Science 6, Mathematics 6, Elective | Biology 4 Level 2 (Science Core), Fillipino 6

Electives: `Elective` for G10 (from B), G11 and G12 (from A) flagged `is_elective`; position 3rd in
each grade (the 10:30–12:00 band). No schedule rows are produced for them.

## Section → room (consistent across all three images)
Identical to the image-1 rewrite; keep as-is:
- G7 Aquamarine→Daffodil, Opal→Anthurium, Sapphire→Calicium, Turquoise→Electron
- G8 Anthurium→Graviton, Carnation→Aquamarine, Daffodil→Lithium, Sunflower→Opal
- G9 Calicium→Neutron, Lithium→Sapphire, Sodium→Turquoise, Barium→Carnation
- G10 Electron→Sunflower, Graviton→Sodium, Proton→Barium, Neutron→Proton
- G11 Mars→Mars, Mercury→Mercury, Neptune→Neptune, Venus→Venus, Class 1→Venus, Class 2→Neptune, Class 3→Mercury
- G12 Class 1→Orosa, Class 2→Del Mundo, Del Mundo→Del Mundo, Orosa→Orosa, Zara→Zara

## Subject-teacher assignments (union across all three images)
Per `(grade, subject)` → section mapping. `all` = every section of that grade.

**G7**
- Mathematics 1: Aquamarine/Opal/Turquoise→Almocera, Divine Faith G.; Sapphire→Pescueso, Brigette Ursula L.
- Earth Science: Aquamarine/Opal→Hijastro, Jhon Ryan P.; Sapphire/Turquoise→Sistoso, Reymond A.
- Social Science 1: all→Orbegoso, Jenny B.
- Filipino 1: all→Asilom, Baby Jean P.
- Computer Science 1: all→Payao, Loida Mae U.
- English 1: all→Subla, Lily Dale M.
- Integrated Science: Aquamarine/Opal/Turquoise→Francisco, Rotchie Glen A.; Sapphire→Hijastro, Jhon Ryan P.

**G8**
- English 2: all→**Ahon, Vonna Vejle V.**
- Mathematics 2B: all→**Salvan, Vedy Von P.**
- Social Science 2: all→Salang, Keith R.
- Earth Science: all→Plaza, Maria Bernadette C.
- Filipino 2: all→Lucresio, Jonnamae Gracia T.
- Mathematics 2A: all→Altar, Daisyre Mae G.
- Biology 1: all→Galliguez, Ethel M.
- Physics 1: all→Segundino, Ken Wood L.
- Chemistry 1: all→Baloria, Noah T.

**G9**
- Social Science 3: Calicium/Lithium→Bernales, Sidney Marie S.; Sodium/Barium→**Villareiz, Jestoni M.**
- Statistics 1: Calicium/Sodium→Lao, Melchizedek Y.; Lithium/Barium→Alerta, Gilbert
- Chemistry 2: all→Perocho, Jayryn J.
- Mathematics 3: all→Pescueso, Brigette Ursula L.
- Filipino 3: all→Mahinay, Nikki Lou L.
- Computer Science 3: all→Fernando, Alvin C.
- Biology 2: all→Maique, Jay M.
- English 3: all→Garcia, Kenneth Jeason B.
- Physics 2: all→Llido, Deborah Gwen G.

**G10**
- **Chemistry 2** (was mis-named `Chemistry 3`): all→Demco, Aphryl Danril B.
- Social Science 4: all→Bernales, Sidney Marie S.
- Filipino 4: all→Cuadrazal, Rea Frechie C.
- Mathematics 4: all→Boniel, Charles Daniel V.
- English 4: all→Jenisan, Armari Eljen E.
- Biology 2: all→Alcando, Darrell
- Physics 2: Electron/Proton→Dumaicos, Aviel Sheen V.; Graviton/Neutron→Sala, John Niño C.

**G11**
- Mathematics 5: Mars/Mercury/Neptune/Venus→Mordeno, Patricia Therese M.
- Social Science 5: Mars/Mercury/Neptune/Venus→Villareiz, Jestoni M.
- Physics 3 Level 2 (Science Core): Mars→Dumaicos, Aviel Sheen V.
- Biology 3 Level 2 (Science Core): Class 1→Penados, John Michael; Class 2→Ganzon, Mary Ann M.; Class 3→Alcando, Darrell
- Fillipino 5: Mars/Mercury/Neptune/Venus→Mordeno, Yvonne M.

**G12**
- Social Science 6: Del Mundo/Orosa/Zara→Abamonga, Maricris C.
- Mathematics 6: Del Mundo/Orosa/Zara→Empuesto, Gretchen Mae B.
- Biology 4 Level 2 (Science Core): Class 1→Ganzon, Mary Ann M.; Class 2→Bayron, Janina Erika
- Fillipino 6: Del Mundo/Orosa/Zara→Dechusa, John Ridan D.

## Fixes vs the pre-rewrite seeder
1. G7 Integrated Science Sapphire → Hijastro (original had all→Francisco).
2. G8 English 2 teacher → `Ahon, Vonna Vejle V.` (original `Añon, Verna Velia V.`); add the teacher.
3. G8 Mathematics 2B teacher → `Salvan, Vedy Von P.` (original `Wendy Von P.`).
4. G9 Social Science 3 add Sodium/Barium → Villareiz.
5. G10 subject rename `Chemistry 3` → `Chemistry 2`, all→Demco; add teacher Demco (exists).
6. G10 Physics 2 fix swap: Graviton→Sala, Proton→Dumaicos.
7. Add `Elective` (is_elective) for G11 and G12.
8. Roster = union of all names in the three images; drop names not present; use image spellings
   (`Chavez, Hernan`; `Principe, Giuseppe A.`; `San Miguel, Alexis Dave`; `Valencia, Ma.Riza F.`;
   `Ahon, Vonna Vejle V.`; `Salvan, Vedy Von P.`).

## Teachers (union)
Include every `teacher` + `proctor` name from all three references (approx. 60–70 people), each
with a `Teacher` row + `User` login (existing pattern). Ensure these are present:
Almocera, Altar, Abrea, Abamonga, Ahon, Alerta, Alcando, Asilom, Baje, Baloria, Bayron, Bermoy,
Bernales, Bohol, Boniel, Chavez, Cuadrazal, Dechusa, Demco, Dominguez, Dumaicos, Empuesto, Fernando
(Alvin), Fernando (Chardy), Francisco, Fulay, Galeon, Galliguez, Ganzon, Garcia, Garrido, Grado,
Hijastro, Jenisan, Lao, Llido, Lucresio, Mahinay, Maique, Morales, Mordeno (Patricia), Mordeno
(Yvonne), Nambatac, Nuñez, Orbegoso, Payao, Penados, Perocho, Pescueso, Plaza, Principe, Sala,
Salang, Salvan, San Miguel, Sanchez, Segundino, Sionosa, Sistoso, Subla, Valencia, Villareiz, Yamit.

## Time preset
Model limitation: `time_preset_slots` is per **grade**, not per day, but the images use different
bands on different days. Use the day-1 band where it exists and day-2 for G11/G12:
- Default (G7/G8/G9): `08:00-09:00`, `09:30-10:30`, `11:00-12:00`
- G10/G11/G12 specific: `07:30-08:30`, `09:00-10:00`, `10:30-12:00`

Document in a code comment that per-day times can't be represented yet.

## Group
`Demo Exam Day` stays a **Draft** group, `days = 3`, `exams_per_day = 3`,
`max_proctors_per_teacher = 2`, attached to all sections, subjects synced with per-grade
`sort_order` in the order above.

## Validation
- `php artisan migrate:fresh --seed`
- `vendor\bin\pint database/seeders/DemoDataSeeder.php --format agent`
- `vendor\bin\pest --compact` (expect failures until the enrollment-semantics changes above land;
  update `ScheduleGeneratorTest` / `ScheduleGroupUnplacedTest` accordingly)
- Tinker check: counts (grades 6, sections 28, subjects ~22 with electives, teachers ~65,
  assignments ≈ sum of reference rows), rooms per section, per-grade subject order, and a
  `ScheduleGenerator::generate` smoke run reporting placed/unplaced/warnings.

## Adjustment: Class 1/2/3 are mixed-enrollment (elective/core) sections
Confirmed from the references: G11 `Class 1/2/3` and G12 `Class 1/2` appear **only** under the
Science Core exam (`Biology 3 Level 2` → Class 1/2/3; `Biology 4 Level 2` → Class 1/2) and the
`ELECTIVE EXAM` placeholder. They never appear under the regular subjects (Mathematics/Social
Science/Filipino/English), and the regular sections never appear under the Class-only Science Core
(`Physics 3 Level 2` → Mars only). Participation is therefore **per (section, subject)**, not
"every section of the grade".

Code implications (beyond the seeder):
1. `ScheduleGenerator` (`app/Services/ScheduleGenerator.php:120-134`): only iterate sections that have
   a `section_subject_teacher` row for the subject. **No row = not enrolled → skip** (emit no unplaced
   entry). A row whose `teacher_id` is null → `missing_teacher` unplaced. (Today: every section of the
   grade is iterated and a missing row becomes `missing_teacher`.)
   `subjectTeacherMap()` must also surface enrolled-but-null pairs (or load an enrollment set).
2. Grids (`Show.vue` and `Public/Schedules.vue` `buildBlocks`): render a section row for a subject only
   when that section is enrolled (mapping/schedule), so Class sections don't show empty under regular
   subjects and vice versa. Public needs the group's enrollment passed from `PublicScheduleController`.
3. `SectionController::assignTeachers` keeps delete-on-clear (= un-enroll). The only null-teacher rows
   come from a deleted teacher (FK `nullOnDelete`), which surface as `missing_teacher`.
4. Test/feature impact: `ScheduleGeneratorTest::reports an item that has no assigned teacher` becomes
   "section not enrolled → skipped"; add a true missing-teacher case (enrolled, null teacher). The
   `ScheduleGroupUnplacedTest` fixtures must enroll only the sections under test.

Seeder: Class sections get **one** assignment each to their Science Core (Class 1/2/3 → Biology 3;
Class 1/2 → Biology 4) and nothing else; `Elective` stays `is_elective` (no rows).

## Risks / assumptions
- **Day alignment across grades isn't exact**: the generator chunks each grade's subjects by
  `exams_per_day`, so grades whose subject count isn't a multiple of 3 (G7=7, G10=8, G11=6, G12=5)
  won't land the same subject on the same day as the source image. This is inherent to the locked
  design (`1791736445583`), not fixable in the seeder.
- **Per-day time bands are not representable** (one preset per group); a single band per grade is
  chosen. Flag for a possible future schema change (day-scoped slots).
- Proctors are **not** seeded (generated); only the people are seeded so they're selectable.
- **`missing_teacher` unplaced becomes rare**: once absence of a mapping means "not enrolled",
  the only way to get a missing-teacher item is an enrolled pair with a null `teacher_id` (e.g. a
  deleted teacher). The old demo gap (seeded mismatches) no longer produces unplaced items.
- Spelling is taken literally from the images (`Calicium`, `Fillipino`).
