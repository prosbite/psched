<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Quarter;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * The union of the three assessment-day references the demo dataset is extracted from.
     *
     * Each row is one subject sitting in one grade on one reference day, together with the
     * sections that sit it and the subject teacher that handles each of those sections:
     * [day, grade, subject, sections, teacher].
     *
     * The three references map to the three exam days: day 1 = "B", day 2 = "A", day 3 = "#1".
     * A subject that is handled by different teachers per section is split into two rows. Rooms and
     * the day/period of each subject come from {@see SUBJECT_ORDER}, {@see SECTION_ROOMS} and
     * {@see TIME_BANDS}; proctors are not seeded because the generator assigns them.
     *
     * @var array<int, array{day: int, grade: string, subject: string, sections: array<int, string>, teacher: string}>
     */
    private const REFERENCE = [
        // Grade 7
        ['day' => 1, 'grade' => '7', 'subject' => 'Mathematics 1', 'sections' => ['Aquamarine', 'Opal', 'Turquoise'], 'teacher' => 'Almocera, Divine Faith G.'],
        ['day' => 1, 'grade' => '7', 'subject' => 'Mathematics 1', 'sections' => ['Sapphire'], 'teacher' => 'Pescueso, Brigette Ursula L.'],
        ['day' => 1, 'grade' => '7', 'subject' => 'Earth Science', 'sections' => ['Aquamarine', 'Opal'], 'teacher' => 'Hijastro, Jhon Ryan P.'],
        ['day' => 1, 'grade' => '7', 'subject' => 'Earth Science', 'sections' => ['Sapphire', 'Turquoise'], 'teacher' => 'Sistoso, Reymond A.'],
        ['day' => 1, 'grade' => '7', 'subject' => 'Social Science 1', 'sections' => ['Aquamarine', 'Opal', 'Sapphire', 'Turquoise'], 'teacher' => 'Orbegoso, Jenny B.'],
        ['day' => 2, 'grade' => '7', 'subject' => 'Filipino 1', 'sections' => ['Aquamarine', 'Opal', 'Sapphire', 'Turquoise'], 'teacher' => 'Asilom, Baby Jean P.'],
        ['day' => 2, 'grade' => '7', 'subject' => 'Computer Science 1', 'sections' => ['Aquamarine', 'Opal', 'Sapphire', 'Turquoise'], 'teacher' => 'Payao, Loida Mae U.'],
        ['day' => 3, 'grade' => '7', 'subject' => 'English 1', 'sections' => ['Aquamarine', 'Opal', 'Sapphire', 'Turquoise'], 'teacher' => 'Subla, Lily Dale M.'],
        ['day' => 3, 'grade' => '7', 'subject' => 'Integrated Science', 'sections' => ['Aquamarine', 'Opal', 'Turquoise'], 'teacher' => 'Francisco, Rotchie Glen A.'],
        ['day' => 3, 'grade' => '7', 'subject' => 'Integrated Science', 'sections' => ['Sapphire'], 'teacher' => 'Hijastro, Jhon Ryan P.'],

        // Grade 8
        ['day' => 1, 'grade' => '8', 'subject' => 'English 2', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Ahon, Vonna Vejle V.'],
        ['day' => 1, 'grade' => '8', 'subject' => 'Mathematics 2B', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Salvan, Vedy Von P.'],
        ['day' => 1, 'grade' => '8', 'subject' => 'Social Science 2', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Salang, Keith R.'],
        ['day' => 2, 'grade' => '8', 'subject' => 'Earth Science', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Plaza, Maria Bernadette C.'],
        ['day' => 2, 'grade' => '8', 'subject' => 'Filipino 2', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Lucresio, Jonnamae Gracia T.'],
        ['day' => 2, 'grade' => '8', 'subject' => 'Mathematics 2A', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Altar, Daisyre Mae G.'],
        ['day' => 3, 'grade' => '8', 'subject' => 'Biology 1', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Galliguez, Ethel M.'],
        ['day' => 3, 'grade' => '8', 'subject' => 'Physics 1', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Segundino, Ken Wood L.'],
        ['day' => 3, 'grade' => '8', 'subject' => 'Chemistry 1', 'sections' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'], 'teacher' => 'Baloria, Noah T.'],

        // Grade 9
        ['day' => 1, 'grade' => '9', 'subject' => 'Social Science 3', 'sections' => ['Calicium', 'Lithium'], 'teacher' => 'Bernales, Sidney Marie S.'],
        ['day' => 1, 'grade' => '9', 'subject' => 'Social Science 3', 'sections' => ['Sodium', 'Barium'], 'teacher' => 'Villareiz, Jestoni M.'],
        ['day' => 1, 'grade' => '9', 'subject' => 'Statistics 1', 'sections' => ['Calicium', 'Sodium'], 'teacher' => 'Lao, Melchizedek Y.'],
        ['day' => 1, 'grade' => '9', 'subject' => 'Statistics 1', 'sections' => ['Lithium', 'Barium'], 'teacher' => 'Alerta, Gilbert'],
        ['day' => 1, 'grade' => '9', 'subject' => 'Chemistry 2', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Perocho, Jayryn J.'],
        ['day' => 2, 'grade' => '9', 'subject' => 'Mathematics 3', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Pescueso, Brigette Ursula L.'],
        ['day' => 2, 'grade' => '9', 'subject' => 'Filipino 3', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Mahinay, Nikki Lou L.'],
        ['day' => 2, 'grade' => '9', 'subject' => 'Computer Science 3', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Fernando, Alvin C.'],
        ['day' => 3, 'grade' => '9', 'subject' => 'Biology 2', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Maique, Jay M.'],
        ['day' => 3, 'grade' => '9', 'subject' => 'English 3', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Garcia, Kenneth Jeason B.'],
        ['day' => 3, 'grade' => '9', 'subject' => 'Physics 2', 'sections' => ['Calicium', 'Lithium', 'Sodium', 'Barium'], 'teacher' => 'Llido, Deborah Gwen G.'],

        // Grade 10
        ['day' => 1, 'grade' => '10', 'subject' => 'Chemistry 2', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Demco, Aphryl Danril B.'],
        ['day' => 1, 'grade' => '10', 'subject' => 'Social Science 4', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Bernales, Sidney Marie S.'],
        ['day' => 2, 'grade' => '10', 'subject' => 'Filipino 4', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Cuadrazal, Rea Frechie C.'],
        ['day' => 2, 'grade' => '10', 'subject' => 'Mathematics 4', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Boniel, Charles Daniel V.'],
        ['day' => 3, 'grade' => '10', 'subject' => 'English 4', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Jenisan, Armari Eljen E.'],
        ['day' => 3, 'grade' => '10', 'subject' => 'Biology 2', 'sections' => ['Electron', 'Graviton', 'Proton', 'Neutron'], 'teacher' => 'Alcando, Darrell'],
        ['day' => 3, 'grade' => '10', 'subject' => 'Physics 2', 'sections' => ['Electron', 'Proton'], 'teacher' => 'Dumaicos, Aviel Sheen V.'],
        ['day' => 3, 'grade' => '10', 'subject' => 'Physics 2', 'sections' => ['Graviton', 'Neutron'], 'teacher' => 'Sala, John Niño C.'],

        // Grade 11
        ['day' => 2, 'grade' => '11', 'subject' => 'Mathematics 5', 'sections' => ['Mars', 'Mercury', 'Neptune', 'Venus'], 'teacher' => 'Mordeno, Patricia Therese M.'],
        ['day' => 2, 'grade' => '11', 'subject' => 'Social Science 5', 'sections' => ['Mars', 'Mercury', 'Neptune', 'Venus'], 'teacher' => 'Villareiz, Jestoni M.'],
        ['day' => 3, 'grade' => '11', 'subject' => 'Physics 3 Level 2 (Science Core)', 'sections' => ['Mars'], 'teacher' => 'Dumaicos, Aviel Sheen V.'],
        ['day' => 3, 'grade' => '11', 'subject' => 'Biology 3 Level 2 (Science Core)', 'sections' => ['Class 1'], 'teacher' => 'Penados, John Michael'],
        ['day' => 3, 'grade' => '11', 'subject' => 'Biology 3 Level 2 (Science Core)', 'sections' => ['Class 2'], 'teacher' => 'Ganzon, Mary Ann M.'],
        ['day' => 3, 'grade' => '11', 'subject' => 'Biology 3 Level 2 (Science Core)', 'sections' => ['Class 3'], 'teacher' => 'Alcando, Darrell'],
        ['day' => 3, 'grade' => '11', 'subject' => 'Fillipino 5', 'sections' => ['Mars', 'Mercury', 'Neptune', 'Venus'], 'teacher' => 'Mordeno, Yvonne M.'],

        // Grade 12
        ['day' => 2, 'grade' => '12', 'subject' => 'Social Science 6', 'sections' => ['Del Mundo', 'Orosa', 'Zara'], 'teacher' => 'Abamonga, Maricris C.'],
        ['day' => 2, 'grade' => '12', 'subject' => 'Mathematics 6', 'sections' => ['Del Mundo', 'Orosa', 'Zara'], 'teacher' => 'Empuesto, Gretchen Mae B.'],
        ['day' => 3, 'grade' => '12', 'subject' => 'Biology 4 Level 2 (Science Core)', 'sections' => ['Class 1'], 'teacher' => 'Ganzon, Mary Ann M.'],
        ['day' => 3, 'grade' => '12', 'subject' => 'Biology 4 Level 2 (Science Core)', 'sections' => ['Class 2'], 'teacher' => 'Bayron, Janina Erika'],
        ['day' => 3, 'grade' => '12', 'subject' => 'Fillipino 6', 'sections' => ['Del Mundo', 'Orosa', 'Zara'], 'teacher' => 'Dechusa, John Ridan D.'],
    ];

    /**
     * Ordered subjects per grade, in the order they sit across the three exam days.
     *
     * This is the source of the group's per-grade `sort_order`; the generator chunks it by
     * `exams_per_day` to derive each subject's day and period. Electives (`Elective` for grades 10
     * to 12) are included at their third position but produce no schedule rows.
     *
     * @var array<string, array<int, string>>
     */
    private const SUBJECT_ORDER = [
        '7' => ['Mathematics 1', 'Earth Science', 'Social Science 1', 'Filipino 1', 'Computer Science 1', 'English 1', 'Integrated Science'],
        '8' => ['English 2', 'Mathematics 2B', 'Social Science 2', 'Earth Science', 'Filipino 2', 'Mathematics 2A', 'Biology 1', 'Physics 1', 'Chemistry 1'],
        '9' => ['Social Science 3', 'Statistics 1', 'Chemistry 2', 'Mathematics 3', 'Filipino 3', 'Computer Science 3', 'Biology 2', 'English 3', 'Physics 2'],
        '10' => ['Chemistry 2', 'Social Science 4', 'Elective', 'Filipino 4', 'Mathematics 4', 'English 4', 'Biology 2', 'Physics 2'],
        '11' => ['Mathematics 5', 'Social Science 5', 'Elective', 'Physics 3 Level 2 (Science Core)', 'Biology 3 Level 2 (Science Core)', 'Fillipino 5'],
        '12' => ['Social Science 6', 'Mathematics 6', 'Elective', 'Biology 4 Level 2 (Science Core)', 'Fillipino 6'],
    ];

    /**
     * Elective subjects per grade. They hold a slot in the timetable but yield no schedule rows.
     *
     * @var array<string, string>
     */
    private const ELECTIVES = [
        '10' => 'Elective',
        '11' => 'Elective',
        '12' => 'Elective',
    ];

    /**
     * Section -> room assignment per grade (consistent across all three references).
     *
     * Rooms are shared records matched by name; several room names repeat across sections.
     *
     * @var array<string, array<string, string>>
     */
    private const SECTION_ROOMS = [
        '7' => ['Aquamarine' => 'Daffodil', 'Opal' => 'Anthurium', 'Sapphire' => 'Calicium', 'Turquoise' => 'Electron'],
        '8' => ['Anthurium' => 'Graviton', 'Carnation' => 'Aquamarine', 'Daffodil' => 'Lithium', 'Sunflower' => 'Opal'],
        '9' => ['Calicium' => 'Neutron', 'Lithium' => 'Sapphire', 'Sodium' => 'Turquoise', 'Barium' => 'Carnation'],
        '10' => ['Electron' => 'Sunflower', 'Graviton' => 'Sodium', 'Proton' => 'Barium', 'Neutron' => 'Proton'],
        '11' => ['Mars' => 'Mars', 'Mercury' => 'Mercury', 'Neptune' => 'Neptune', 'Venus' => 'Venus', 'Class 1' => 'Venus', 'Class 2' => 'Neptune', 'Class 3' => 'Mercury'],
        '12' => ['Class 1' => 'Orosa', 'Class 2' => 'Del Mundo', 'Del Mundo' => 'Del Mundo', 'Orosa' => 'Orosa', 'Zara' => 'Zara'],
    ];

    /**
     * The default time band (grade scope `null`), indexed by period.
     *
     * The references use a different band per exam day, but `time_preset_slots` is scoped per grade
     * rather than per day, so a single band per grade is chosen here and per-day times cannot be
     * represented until the schema gains day-scoped slots.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DEFAULT_TIME_BANDS = [
        ['08:00', '09:00'],
        ['09:30', '10:30'],
        ['11:00', '12:00'],
    ];

    /**
     * Grade-specific time bands that override the default (grades 10 to 12 offset 30 minutes).
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const TIME_BANDS = [
        '10' => [['07:30', '08:30'], ['09:00', '10:00'], ['10:30', '12:00']],
        '11' => [['07:30', '08:30'], ['09:00', '10:00'], ['10:30', '12:00']],
        '12' => [['07:30', '08:30'], ['09:00', '10:00'], ['10:30', '12:00']],
    ];

    /**
     * Proctor-only people (they never appear in the reference `teacher` column).
     *
     * The full roster is the union of every teacher in {@see REFERENCE} and these people, so that
     * every proctor name shown in the references is selectable; the generator still assigns the
     * actual proctors.
     *
     * @var array<int, string>
     */
    private const PROCTORS = [
        'Abrea, Charmaine B.',
        'Baguio, Louege N.',
        'Baje, Benito A.',
        'Bermoy, Lyndon R.',
        'Bohol, Jan Grenechaux V.',
        'Chavez, Hernan',
        'Dominguez, Marchone O',
        'Fernando, Chardy C.',
        'Fernando, Michelle B.',
        'Fulay, Louren P.',
        'Galeon, Jesse Vim R.',
        'Garrido, Hannah Elizabeth P.',
        'Grado, JL-Joshua B.',
        'Gumapac, Jasmine S.',
        'Lozano, Liezl Mae B.',
        'Morales, Shriegley Mae R.',
        'Nemeño, Nestor "Bong" Jr. B.',
        'Nuñez, Nerry C.',
        'Principe, Giuseppe A.',
        'Ramayla, Sherry P.',
        'San Miguel, Alexis Dave',
        'Sanchez, Jecelyn E.',
        'Sionosa, Glenn M.',
        'Tarre, Merriame Danielle G.',
        'Valencia, Ma.Riza F.',
        'Yamit, Marian Mae M.',
    ];

    /**
     * Seed the demo dataset extracted from the three assessment-day references.
     */
    public function run(): void
    {
        $gradeLevels = [];

        foreach (array_keys(self::SUBJECT_ORDER) as $grade) {
            $gradeLevels[$grade] = GradeLevel::firstOrCreate([
                'grade_level' => $grade,
            ]);
        }

        $subjects = $this->seedSubjects($gradeLevels);
        $sections = $this->seedSections($gradeLevels);

        $teachers = $this->seedTeachers();

        $this->assignSubjectTeachers($subjects, $sections, $teachers);

        $this->seedScheduleGroup($gradeLevels, $subjects);
    }

    /**
     * Seed the unique subjects, keyed by grade level then subject name.
     *
     * @param  array<string, GradeLevel>  $gradeLevels
     * @return array<string, Subject>
     */
    private function seedSubjects(array $gradeLevels): array
    {
        $subjects = [];

        foreach (self::SUBJECT_ORDER as $grade => $names) {
            foreach ($names as $name) {
                $subjects[$grade.'|'.$name] = Subject::firstOrCreate(
                    [
                        'subject_name' => $name,
                        'grade_level_id' => $gradeLevels[$grade]->id,
                    ],
                    [
                        'is_elective' => (self::ELECTIVES[$grade] ?? null) === $name,
                    ],
                );
            }
        }

        return $subjects;
    }

    /**
     * Seed the unique sections with their assigned rooms, keyed by grade level then section name.
     *
     * @param  array<string, GradeLevel>  $gradeLevels
     * @return array<string, Section>
     */
    private function seedSections(array $gradeLevels): array
    {
        $sections = [];

        foreach (self::SECTION_ROOMS as $grade => $rooms) {
            foreach ($rooms as $sectionName => $roomName) {
                $section = Section::firstOrCreate([
                    'section_name' => $sectionName,
                    'grade_level_id' => $gradeLevels[$grade]->id,
                ]);

                $room = Room::firstOrCreate(['room_name' => $roomName]);

                $section->update(['room_id' => $room->id]);

                $sections[$grade.'|'.$sectionName] = $section;
            }
        }

        return $sections;
    }

    /**
     * Split a "Lastname, Firstname M." name into its parts.
     *
     * @return array{lastname: string, firstname: string, middlename: string|null}
     */
    private function parseTeacherName(string $name): array
    {
        [$lastname, $rest] = array_map('trim', explode(',', $name, 2));

        $parts = preg_split('/\s+/', $rest) ?: [];

        $middlename = null;

        if (count($parts) > 1 && preg_match('/^[A-Za-z]\.?$/', end($parts)) === 1) {
            $middlename = array_pop($parts);
        }

        return [
            'lastname' => $lastname,
            'firstname' => implode(' ', $parts),
            'middlename' => $middlename,
        ];
    }

    /**
     * Seed every person in the roster (each with a login), keyed by the name used in the data.
     *
     * The roster is the union of all reference subject teachers and the proctor-only people.
     *
     * @return array<string, Teacher>
     */
    private function seedTeachers(): array
    {
        $teachers = [];

        $names = array_merge(
            array_map(fn (array $row): string => $row['teacher'], self::REFERENCE),
            self::PROCTORS,
        );

        foreach ($names as $name) {
            if (isset($teachers[$name])) {
                continue;
            }

            $data = $this->parseTeacherName($name);

            $email = Str::slug($data['lastname']).'.'.Str::slug($data['firstname']).'@example.com';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$data['firstname']} {$data['lastname']}",
                    'password' => 'password',
                    'role' => UserRole::Teacher,
                    'email_verified_at' => now(),
                ],
            );

            $teachers[$name] = Teacher::firstOrCreate(
                [
                    'lastname' => $data['lastname'],
                    'firstname' => $data['firstname'],
                    'middlename' => $data['middlename'],
                ],
                [
                    'user_id' => $user->id,
                    'email' => $email,
                ],
            );
        }

        return $teachers;
    }

    /**
     * Assign exactly one teacher to each (section, subject) pair from the references.
     *
     * @param  array<string, Subject>  $subjects
     * @param  array<string, Section>  $sections
     * @param  array<string, Teacher>  $teachers
     */
    private function assignSubjectTeachers(array $subjects, array $sections, array $teachers): void
    {
        foreach (self::REFERENCE as $row) {
            $subject = $subjects[$row['grade'].'|'.$row['subject']] ?? null;
            $teacher = $teachers[$row['teacher']] ?? null;

            if ($subject === null || $teacher === null) {
                continue;
            }

            foreach ($row['sections'] as $sectionName) {
                $section = $sections[$row['grade'].'|'.$sectionName] ?? null;

                if ($section === null) {
                    continue;
                }

                SectionSubjectTeacher::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                    ],
                );
            }
        }
    }

    /**
     * Seed the reusable time preset and a draft schedule group covering all three exam days.
     *
     * @param  array<string, GradeLevel>  $gradeLevels
     * @param  array<string, Subject>  $subjects
     */
    private function seedScheduleGroup(array $gradeLevels, array $subjects): void
    {
        $preset = TimePreset::firstOrCreate(['name' => 'Standard Exam Day']);

        $preset->slots()->delete();

        foreach (self::DEFAULT_TIME_BANDS as $period => [$start, $end]) {
            $preset->slots()->create([
                'grade_level_id' => null,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'sort_order' => $period,
            ]);
        }

        foreach (self::TIME_BANDS as $grade => $bands) {
            foreach ($bands as $period => [$start, $end]) {
                $preset->slots()->create([
                    'grade_level_id' => $gradeLevels[$grade]->id,
                    'start_time' => $start.':00',
                    'end_time' => $end.':00',
                    'sort_order' => $period,
                ]);
            }
        }

        $schoolYear = SchoolYear::orderByDesc('name')->first() ?? SchoolYear::factory()->create();
        $quarter = Quarter::orderBy('start_date')->first() ?? Quarter::factory()->create();

        $group = ScheduleGroup::firstOrCreate(
            ['title' => 'Demo Exam Day'],
            [
                'description' => 'A three-day sample assessment for Grades 7 to 12.',
                'days' => 3,
                'exams_per_day' => 3,
                'time_preset_id' => $preset->id,
                'school_year_id' => $schoolYear->id,
                'quarter_id' => $quarter->id,
                'max_proctors_per_teacher' => 2,
            ],
        );

        $group->update([
            'time_preset_id' => $preset->id,
            'days' => 3,
            'exams_per_day' => 3,
            'school_year_id' => $schoolYear->id,
            'quarter_id' => $quarter->id,
        ]);

        $group->sections()->sync(Section::pluck('id')->all());

        $sync = [];

        foreach (self::SUBJECT_ORDER as $grade => $names) {
            foreach ($names as $order => $name) {
                $subject = $subjects[$grade.'|'.$name];

                $sync[$subject->id] = ['sort_order' => $order];
            }
        }

        $group->subjects()->sync($sync);
    }
}
