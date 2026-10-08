<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Sections per grade level.
     *
     * @var array<string, array<int, string>>
     */
    private const SECTIONS = [
        '7' => ['Aquamarine', 'Opal', 'Sapphire', 'Turquoise'],
        '8' => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'],
        '9' => ['Calcium', 'Lithium', 'Sodium', 'Barium'],
        '10' => ['Electron', 'Graviton', 'Proton', 'Neutron'],
        '11' => ['Mars', 'Mercury', 'Neptune', 'Venus', 'Class 1', 'Class 2', 'Class 3'],
        '12' => ['Del Mundo', 'Orosa', 'Zara', 'Class 1', 'Class 2'],
    ];

    /**
     * Subjects per grade level.
     *
     * @var array<string, array<int, string>>
     */
    private const SUBJECTS = [
        '7' => ['Mathematics 1', 'Earth Science', 'Social Science 1', 'Filipino 1', 'Computer Science 1', 'English 1', 'Integrated Science'],
        '8' => ['English 2', 'Mathematics 2B', 'Social Science 2', 'Earth Science', 'Filipino 2', 'Mathematics 2A', 'Biology 1', 'Physics 1', 'Chemistry 1'],
        '9' => ['Social Science 3', 'Statistics 1', 'Chemistry 2', 'Mathematics 3', 'Filipino 3', 'Computer Science 3', 'Biology 2', 'English 3', 'Physics 2'],
        '10' => ['Chemistry 3', 'Social Science 4', 'Filipino 4', 'Mathematics 4', 'English 4', 'Biology 2', 'Physics 2'],
        '11' => ['Mathematics 5', 'Social Science 5', 'Physics 3 Level 2 (Science Core)', 'Biology 3 Level 2 (Science Core)', 'Filipino 5'],
        '12' => ['Social Science 6', 'Mathematics 6', 'Biology 4 Level 2 (Science Core)', 'Filipino 6'],
    ];

    /**
     * The unique teachers in the data, keyed by the name used in the mapping.
     *
     * @var array<string, array{lastname: string, firstname: string, middlename: string|null}>
     */
    private const TEACHERS = [
        'Almocera, Divine Faith G.' => ['lastname' => 'Almocera', 'firstname' => 'Divine Faith', 'middlename' => 'G.'],
        'Pascual, Brigette Ursula L.' => ['lastname' => 'Pascual', 'firstname' => 'Brigette Ursula', 'middlename' => 'L.'],
        'Hijan, Jhon Ryan P.' => ['lastname' => 'Hijan', 'firstname' => 'Jhon Ryan', 'middlename' => 'P.'],
        'Salazar, Raymond A.' => ['lastname' => 'Salazar', 'firstname' => 'Raymond', 'middlename' => 'A.'],
        'Ortigosa, Jenny B.' => ['lastname' => 'Ortigosa', 'firstname' => 'Jenny', 'middlename' => 'B.'],
        'Añon, Verna Velia V.' => ['lastname' => 'Añon', 'firstname' => 'Verna Velia', 'middlename' => 'V.'],
        'Salvan, Wendy Von P.' => ['lastname' => 'Salvan', 'firstname' => 'Wendy Von', 'middlename' => 'P.'],
        'Salang, Keith R.' => ['lastname' => 'Salang', 'firstname' => 'Keith', 'middlename' => 'R.'],
        'Bernales, Sichey Marie S.' => ['lastname' => 'Bernales', 'firstname' => 'Sichey Marie', 'middlename' => 'S.'],
        'Uliareb, Jeston M.' => ['lastname' => 'Uliareb', 'firstname' => 'Jeston', 'middlename' => 'M.'],
        'Lao, Melchizedek Y.' => ['lastname' => 'Lao', 'firstname' => 'Melchizedek', 'middlename' => 'Y.'],
        'Alerio, Gilbert' => ['lastname' => 'Alerio', 'firstname' => 'Gilbert', 'middlename' => null],
        'Parocho, Jayryn J.' => ['lastname' => 'Parocho', 'firstname' => 'Jayryn', 'middlename' => 'J.'],
        'Damco, Ashlyn Sarah B.' => ['lastname' => 'Damco', 'firstname' => 'Ashlyn Sarah', 'middlename' => 'B.'],
        'Asilom, Baby Jean P.' => ['lastname' => 'Asilom', 'firstname' => 'Baby Jean', 'middlename' => 'P.'],
        'Loida Mae U. Payao' => ['lastname' => 'Payao', 'firstname' => 'Loida Mae', 'middlename' => 'U.'],
        'Plaza, Maria Bernadette' => ['lastname' => 'Plaza', 'firstname' => 'Maria Bernadette', 'middlename' => null],
        'Lucresio, Jonnamae Gracia T.' => ['lastname' => 'Lucresio', 'firstname' => 'Jonnamae Gracia', 'middlename' => 'T.'],
        'Altar, Daisyre Mae G.' => ['lastname' => 'Altar', 'firstname' => 'Daisyre Mae', 'middlename' => 'G.'],
        'Pescueso, Brigette Ursula L.' => ['lastname' => 'Pescueso', 'firstname' => 'Brigette Ursula', 'middlename' => 'L.'],
        'Mahinay, Nikki Lou L.' => ['lastname' => 'Mahinay', 'firstname' => 'Nikki Lou', 'middlename' => 'L.'],
        'Fernando, Alvin C.' => ['lastname' => 'Fernando', 'firstname' => 'Alvin', 'middlename' => 'C.'],
        'Cuadrazal, Rea Frechie C.' => ['lastname' => 'Cuadrazal', 'firstname' => 'Rea Frechie', 'middlename' => 'C.'],
        'Boniel, Charles Daniel' => ['lastname' => 'Boniel', 'firstname' => 'Charles Daniel', 'middlename' => null],
        'Mordeno, Patricia Therese M.' => ['lastname' => 'Mordeno', 'firstname' => 'Patricia Therese', 'middlename' => 'M.'],
        'Villareiz, Jestoni M.' => ['lastname' => 'Villareiz', 'firstname' => 'Jestoni', 'middlename' => 'M.'],
        'Abamonga, Maricris C.' => ['lastname' => 'Abamonga', 'firstname' => 'Maricris', 'middlename' => 'C.'],
        'Empuesto, Gretchen Mae B.' => ['lastname' => 'Empuesto', 'firstname' => 'Gretchen Mae', 'middlename' => 'B.'],
        'Subla, Lily Dale M.' => ['lastname' => 'Subla', 'firstname' => 'Lily Dale', 'middlename' => 'M.'],
        'Francisco, Rotchie Glen A.' => ['lastname' => 'Francisco', 'firstname' => 'Rotchie Glen', 'middlename' => 'A.'],
        'Galliguez, Ethel M.' => ['lastname' => 'Galliguez', 'firstname' => 'Ethel', 'middlename' => 'M.'],
        'Segundino, Ken Wood L.' => ['lastname' => 'Segundino', 'firstname' => 'Ken Wood', 'middlename' => 'L.'],
        'Baloria, Noah T.' => ['lastname' => 'Baloria', 'firstname' => 'Noah', 'middlename' => 'T.'],
        'Maique, Jay M.' => ['lastname' => 'Maique', 'firstname' => 'Jay', 'middlename' => 'M.'],
        'Garcia, Kenneth Jeason B.' => ['lastname' => 'Garcia', 'firstname' => 'Kenneth Jeason', 'middlename' => 'B.'],
        'Llido, Deborah Gwen G.' => ['lastname' => 'Llido', 'firstname' => 'Deborah Gwen', 'middlename' => 'G.'],
        'Jenisan, Armari Eljen E.' => ['lastname' => 'Jenisan', 'firstname' => 'Armari Eljen', 'middlename' => 'E.'],
        'Alcando, Darell' => ['lastname' => 'Alcando', 'firstname' => 'Darell', 'middlename' => null],
        'Dumaicos, Aviel Sheen V.' => ['lastname' => 'Dumaicos', 'firstname' => 'Aviel Sheen', 'middlename' => 'V.'],
        'Sala, John Niño C.' => ['lastname' => 'Sala', 'firstname' => 'John Niño', 'middlename' => 'C.'],
        'Penados, Jhon Michael' => ['lastname' => 'Penados', 'firstname' => 'Jhon Michael', 'middlename' => null],
        'Ganzon, Mary Ann M.' => ['lastname' => 'Ganzon', 'firstname' => 'Mary Ann', 'middlename' => 'M.'],
        'Mordeno, Yvonne M.' => ['lastname' => 'Mordeno', 'firstname' => 'Yvonne', 'middlename' => 'M.'],
        'Bayron, Janina Erika S.' => ['lastname' => 'Bayron', 'firstname' => 'Janina Erika', 'middlename' => 'S.'],
        'Dechusa, John Ridan D.' => ['lastname' => 'Dechusa', 'firstname' => 'John Ridan', 'middlename' => 'D.'],
    ];

    /**
     * The subject/teacher assignment pairs extracted from the data, per grade.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const SUBJECT_TEACHERS = [
        '7' => [
            'Mathematics 1' => ['Almocera, Divine Faith G.', 'Pascual, Brigette Ursula L.'],
            'Earth Science' => ['Hijan, Jhon Ryan P.', 'Salazar, Raymond A.'],
            'Social Science 1' => ['Ortigosa, Jenny B.'],
            'Filipino 1' => ['Asilom, Baby Jean P.'],
            'Computer Science 1' => ['Loida Mae U. Payao'],
            'English 1' => ['Subla, Lily Dale M.'],
            'Integrated Science' => ['Francisco, Rotchie Glen A.'],
        ],
        '8' => [
            'English 2' => ['Añon, Verna Velia V.'],
            'Mathematics 2B' => ['Salvan, Wendy Von P.'],
            'Social Science 2' => ['Salang, Keith R.'],
            'Earth Science' => ['Plaza, Maria Bernadette'],
            'Filipino 2' => ['Lucresio, Jonnamae Gracia T.'],
            'Mathematics 2A' => ['Altar, Daisyre Mae G.'],
            'Biology 1' => ['Galliguez, Ethel M.'],
            'Physics 1' => ['Segundino, Ken Wood L.'],
            'Chemistry 1' => ['Baloria, Noah T.'],
        ],
        '9' => [
            'Social Science 3' => ['Bernales, Sichey Marie S.', 'Uliareb, Jeston M.'],
            'Statistics 1' => ['Lao, Melchizedek Y.', 'Alerio, Gilbert'],
            'Chemistry 2' => ['Parocho, Jayryn J.'],
            'Mathematics 3' => ['Pescueso, Brigette Ursula L.'],
            'Filipino 3' => ['Mahinay, Nikki Lou L.'],
            'Computer Science 3' => ['Fernando, Alvin C.'],
            'Biology 2' => ['Maique, Jay M.'],
            'English 3' => ['Garcia, Kenneth Jeason B.'],
            'Physics 2' => ['Llido, Deborah Gwen G.'],
        ],
        '10' => [
            'Chemistry 3' => ['Damco, Ashlyn Sarah B.'],
            'Social Science 4' => ['Bernales, Sichey Marie S.'],
            'Filipino 4' => ['Cuadrazal, Rea Frechie C.'],
            'Mathematics 4' => ['Boniel, Charles Daniel'],
            'English 4' => ['Jenisan, Armari Eljen E.'],
            'Biology 2' => ['Alcando, Darell'],
            'Physics 2' => ['Dumaicos, Aviel Sheen V.', 'Sala, John Niño C.'],
        ],
        '11' => [
            'Mathematics 5' => ['Mordeno, Patricia Therese M.'],
            'Social Science 5' => ['Villareiz, Jestoni M.'],
            'Physics 3 Level 2 (Science Core)' => ['Dumaicos, Aviel Sheen V.'],
            'Biology 3 Level 2 (Science Core)' => ['Penados, Jhon Michael', 'Ganzon, Mary Ann M.', 'Alcando, Darell'],
            'Filipino 5' => ['Mordeno, Yvonne M.'],
        ],
        '12' => [
            'Social Science 6' => ['Abamonga, Maricris C.'],
            'Mathematics 6' => ['Empuesto, Gretchen Mae B.'],
            'Biology 4 Level 2 (Science Core)' => ['Ganzon, Mary Ann M.', 'Bayron, Janina Erika S.'],
            'Filipino 6' => ['Dechusa, John Ridan D.'],
        ],
    ];

    /**
     * Seed the demo dataset extracted from the assessment schedule.
     */
    public function run(): void
    {
        $gradeLevels = [];

        foreach (array_keys(self::SECTIONS) as $grade) {
            $gradeLevels[$grade] = GradeLevel::firstOrCreate([
                'grade_level' => $grade,
            ]);
        }

        $subjects = $this->seedSubjects($gradeLevels);
        $this->seedSections($gradeLevels);

        $teachers = $this->seedTeachers();

        $this->assignSubjectTeachers($subjects, $teachers);

        $this->seedScheduleGroup();
    }

    /**
     * Seed the unique subjects and return them keyed by grade level, then name.
     *
     * @param  array<string, GradeLevel>  $gradeLevels
     * @return array<string, array<string, Subject>>
     */
    private function seedSubjects(array $gradeLevels): array
    {
        $subjects = [];

        foreach (self::SUBJECTS as $grade => $names) {
            foreach ($names as $name) {
                $subjects[$grade][$name] = Subject::firstOrCreate([
                    'subject_name' => $name,
                    'grade_level_id' => $gradeLevels[$grade]->id,
                ]);
            }
        }

        return $subjects;
    }

    /**
     * Seed the unique sections, each paired with a room of the same name.
     *
     * @param  array<string, GradeLevel>  $gradeLevels
     */
    private function seedSections(array $gradeLevels): void
    {
        foreach (self::SECTIONS as $grade => $names) {
            foreach ($names as $name) {
                $section = Section::firstOrCreate([
                    'section_name' => $name,
                    'grade_level_id' => $gradeLevels[$grade]->id,
                ]);

                if ($section->room_id === null) {
                    $room = Room::where('room_name', $name)->whereDoesntHave('sections')->first()
                        ?? Room::create(['room_name' => $name]);

                    $section->update(['room_id' => $room->id]);
                }
            }
        }
    }

    /**
     * Seed the unique teachers (each with a login) and return them keyed by the data name.
     *
     * @return array<string, Teacher>
     */
    private function seedTeachers(): array
    {
        $teachers = [];

        foreach (self::TEACHERS as $name => $data) {
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
     * Fetch the seeded subjects and teachers and assign the subject/teacher links.
     *
     * @param  array<string, array<string, Subject>>  $subjects
     * @param  array<string, Teacher>  $teachers
     */
    private function assignSubjectTeachers(array $subjects, array $teachers): void
    {
        foreach (self::SUBJECT_TEACHERS as $grade => $subjectTeachers) {
            foreach ($subjectTeachers as $subjectName => $teacherNames) {
                $teacherIds = collect($teacherNames)
                    ->map(fn (string $name): int => $teachers[$name]->id)
                    ->all();

                $subjects[$grade][$subjectName]->teachers()->syncWithoutDetaching($teacherIds);
            }
        }
    }

    /**
     * Seed the reusable time preset and a draft schedule group.
     */
    private function seedScheduleGroup(): void
    {
        $preset = TimePreset::firstOrCreate(['name' => 'Standard Exam Day']);

        foreach (['07:00', '08:00', '09:00', '10:00', '11:00'] as $index => $start) {
            $preset->slots()->firstOrCreate(
                ['start_time' => sprintf('%s:00', $start)],
                [
                    'end_time' => sprintf('%02d:00:00', ((int) substr($start, 0, 2)) + 1),
                    'sort_order' => $index,
                ],
            );
        }

        $group = ScheduleGroup::firstOrCreate(
            ['title' => 'Demo Exam Day'],
            [
                'description' => 'A sample scheduling day for demonstration.',
                'time_preset_id' => $preset->id,
                'max_proctors_per_teacher' => 2,
            ],
        );

        $group->sections()->syncWithoutDetaching(Section::pluck('id')->all());
        $group->subjects()->syncWithoutDetaching(Subject::pluck('id')->all());
    }
}
