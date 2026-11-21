<?php

namespace Database\Seeders\Demo;

use App\Enums\ClassSection;
use App\Enums\ClassworkStatus;
use App\Enums\QuestionType;
use App\Enums\QuizKind;
use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Term;
use App\Support\Files\StoredFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * This term's classwork for a demo school: for the main subjects in every class from
 * Primary 4 up, a marked assignment, one still open, a finished quiz and one open now.
 * Some hand-ins come with uploaded pages so the marking screen has something to show,
 * and one class has a fresh pile of work waiting to be marked.
 */
final class DemoClasswork
{
    /**
     * Subject code => assignments [title, instructions] and quizzes [title, questions].
     * A question is [type, prompt, options (key => text) or null, answer list, marks].
     *
     * @var array<string, array{assignments: list<array{0: string, 1: string}>, quizzes: list<array{0: string, 1: list<array{0: string, 1: string, 2: array<string, string>|null, 3: list<string>, 4: int}>}>}>
     */
    private const BANK = [
        'MTH' => [
            'assignments' => [
                ['Fractions and decimals', "Do Exercise 3.2 in your textbook, questions 1 to 15.\nShow all your working. Write neatly and number each answer."],
                ['Word problems on simple interest', "Solve the five word problems on page 48.\nWrite each final answer as a full sentence, e.g. \"The interest is ₦4,500.\""],
                ['Angles in a triangle', "Draw three triangles of your own, label the angles, and find the missing angle in each.\nUse a ruler and protractor."],
            ],
            'quizzes' => [
                ['Fractions quick quiz', [
                    ['single', '1/2 + 1/4 = ?', ['A' => '3/4', 'B' => '2/6', 'C' => '1/6', 'D' => '2/4'], ['A'], 2],
                    ['single', 'Write 0.25 as a fraction in its lowest terms.', ['A' => '25/10', 'B' => '1/4', 'C' => '2/5', 'D' => '5/2'], ['B'], 2],
                    ['multiple', 'Which of these are equal to one half?', ['A' => '2/4', 'B' => '3/6', 'C' => '2/3', 'D' => '5/10'], ['A', 'B', 'D'], 2],
                    ['true_false', '3/5 is greater than 1/2.', null, ['true'], 1],
                    ['short', 'What is 3/4 of 20?', null, ['15'], 1],
                ]],
                ['Number bases and indices test', [
                    ['single', 'Simplify 2³ × 2².', ['A' => '2⁵', 'B' => '2⁶', 'C' => '4⁵', 'D' => '4⁶'], ['A'], 2],
                    ['single', 'Convert 1011₂ to base ten.', ['A' => '9', 'B' => '10', 'C' => '11', 'D' => '13'], ['C'], 2],
                    ['true_false', 'Any number raised to the power 0 equals 1.', null, ['true'], 1],
                    ['short', 'Write 25 in base two.', null, ['11001'], 2],
                    ['single', 'Evaluate 10⁻².', ['A' => '-100', 'B' => '0.01', 'C' => '0.1', 'D' => '-20'], ['B'], 1],
                ]],
            ],
        ],
        'ENG' => [
            'assignments' => [
                ['Essay: My most memorable day', "Write an essay of about 300 words on \"My most memorable day\".\nPlan with an introduction, body and conclusion. Check your spelling and punctuation."],
                ['Letter to a friend', "Write an informal letter to a friend in another state telling them about your school's inter-house sports.\nUse the correct format for an informal letter."],
                ['Comprehension: The Village Market', 'Read the passage "The Village Market" on page 62 and answer questions (a) to (h) in full sentences.'],
            ],
            'quizzes' => [
                ['Parts of speech quiz', [
                    ['single', 'In "The tall boy ran quickly", which word is an adverb?', ['A' => 'tall', 'B' => 'boy', 'C' => 'ran', 'D' => 'quickly'], ['D'], 1],
                    ['single', 'Choose the correct plural of "child".', ['A' => 'childs', 'B' => 'children', 'C' => 'childrens', 'D' => 'childes'], ['B'], 1],
                    ['multiple', 'Which of these are pronouns?', ['A' => 'they', 'B' => 'quickly', 'C' => 'her', 'D' => 'market'], ['A', 'C'], 2],
                    ['true_false', '"Lagos" is a proper noun.', null, ['true'], 1],
                    ['short', 'Give the past tense of "go".', null, ['went'], 1],
                ]],
                ['Spelling and vocabulary test', [
                    ['single', 'Choose the correctly spelt word.', ['A' => 'recieve', 'B' => 'receive', 'C' => 'receeve', 'D' => 'riceive'], ['B'], 1],
                    ['single', 'The opposite of "generous" is…', ['A' => 'kind', 'B' => 'mean', 'C' => 'rich', 'D' => 'happy'], ['B'], 1],
                    ['true_false', '"Their" and "there" mean the same thing.', null, ['false'], 1],
                    ['short', 'Give one word that means "very big".', null, ['huge', 'enormous', 'gigantic', 'massive', 'large'], 1],
                ]],
            ],
        ],
        'BSC' => [
            'assignments' => [
                ['States of matter', "List five examples each of solids, liquids and gases found in your home.\nExplain in two sentences what happens to water when it is heated."],
                ['Living and non-living things', 'Draw a table with two columns, Living and Non-living. Put ten things from your environment in it and give one reason for each.'],
            ],
            'quizzes' => [
                ['Matter and energy quiz', [
                    ['single', 'Which of these is a gas at room temperature?', ['A' => 'Water', 'B' => 'Oxygen', 'C' => 'Iron', 'D' => 'Salt'], ['B'], 1],
                    ['true_false', 'Ice melting is a physical change.', null, ['true'], 1],
                    ['single', 'The change from liquid to gas is called…', ['A' => 'freezing', 'B' => 'condensation', 'C' => 'evaporation', 'D' => 'melting'], ['C'], 1],
                    ['multiple', 'Which are sources of energy?', ['A' => 'The sun', 'B' => 'Wind', 'C' => 'Sand', 'D' => 'Food'], ['A', 'B', 'D'], 2],
                ]],
            ],
        ],
        'BIO' => [
            'assignments' => [
                ['The cell', "Draw and label a plant cell and an animal cell.\nState four differences between them."],
                ['Food chains in Nigeria', 'Construct two food chains you can find in a Nigerian savanna, each with at least four organisms. Name the producer and consumers.'],
            ],
            'quizzes' => [
                ['Cell biology test', [
                    ['single', 'Which organelle is called the powerhouse of the cell?', ['A' => 'Nucleus', 'B' => 'Mitochondrion', 'C' => 'Ribosome', 'D' => 'Vacuole'], ['B'], 2],
                    ['true_false', 'Animal cells have a cell wall.', null, ['false'], 1],
                    ['multiple', 'Which are found in plant cells only?', ['A' => 'Chloroplast', 'B' => 'Cell wall', 'C' => 'Nucleus', 'D' => 'Large central vacuole'], ['A', 'B', 'D'], 2],
                    ['short', 'Name the green pigment in leaves.', null, ['chlorophyll'], 1],
                ]],
            ],
        ],
        'PHY' => [
            'assignments' => [
                ['Motion: speed and velocity', "A bus travels from Abuja to Kaduna (180 km) in 2 hours 30 minutes.\nCalculate its average speed in km/h and in m/s. Show your working."],
            ],
            'quizzes' => [
                ['Measurement and units quiz', [
                    ['single', 'The SI unit of force is the…', ['A' => 'joule', 'B' => 'newton', 'C' => 'watt', 'D' => 'pascal'], ['B'], 1],
                    ['single', 'Which is a vector quantity?', ['A' => 'Mass', 'B' => 'Speed', 'C' => 'Velocity', 'D' => 'Time'], ['C'], 1],
                    ['true_false', 'A vernier caliper is more precise than a metre rule.', null, ['true'], 1],
                    ['short', 'How many millimetres are in one metre?', null, ['1000', '1,000'], 1],
                ]],
            ],
        ],
        'CHM' => [
            'assignments' => [
                ['Separation techniques', "Describe how you would separate (a) sand from water, (b) salt from sea water, (c) the dyes in black ink.\nDraw a labelled diagram for one of them."],
            ],
            'quizzes' => [
                ['Atoms and elements quiz', [
                    ['single', 'The chemical symbol for sodium is…', ['A' => 'S', 'B' => 'So', 'C' => 'Na', 'D' => 'Sd'], ['C'], 1],
                    ['single', 'An atom of carbon has how many protons?', ['A' => '4', 'B' => '6', 'C' => '12', 'D' => '14'], ['B'], 1],
                    ['true_false', 'Water is an element.', null, ['false'], 1],
                    ['short', 'What is the chemical formula of water?', null, ['H2O', 'H₂O'], 1],
                ]],
            ],
        ],
        'ECO' => [
            'assignments' => [
                ['Demand and supply', "Explain the law of demand with a well-labelled diagram.\nGive two reasons why the price of garri rises in the rainy season."],
            ],
            'quizzes' => [
                ['Basic concepts quiz', [
                    ['single', 'Opportunity cost is best described as…', ['A' => 'the money paid', 'B' => 'the alternative forgone', 'C' => 'the profit made', 'D' => 'the tax paid'], ['B'], 1],
                    ['true_false', 'Scarcity means human wants are unlimited while resources are limited.', null, ['true'], 1],
                    ['multiple', 'Which are factors of production?', ['A' => 'Land', 'B' => 'Labour', 'C' => 'Money', 'D' => 'Entrepreneur'], ['A', 'B', 'D'], 2],
                ]],
            ],
        ],
        'CVE' => [
            'assignments' => [
                ['Rights and duties of a citizen', "List five rights and five duties of a Nigerian citizen.\nExplain why paying tax is important, in one paragraph."],
            ],
            'quizzes' => [
                ['Citizenship quiz', [
                    ['single', 'Nigeria became independent in…', ['A' => '1957', 'B' => '1960', 'C' => '1963', 'D' => '1966'], ['B'], 1],
                    ['true_false', 'Voting in elections is a civic responsibility.', null, ['true'], 1],
                    ['single', 'The highest law in Nigeria is the…', ['A' => 'Constitution', 'B' => 'Penal Code', 'C' => 'Bye-law', 'D' => 'Decree'], ['A'], 1],
                    ['short', 'What colour is in the middle of the Nigerian flag?', null, ['white'], 1],
                ]],
            ],
        ],
        'SOS' => [
            'assignments' => [
                ['Our culture', 'Write about three cultural festivals in Nigeria. Say where each is celebrated and what happens.'],
            ],
            'quizzes' => [
                ['Family and society quiz', [
                    ['single', 'A family made up of father, mother and children is a…', ['A' => 'nuclear family', 'B' => 'extended family', 'C' => 'clan', 'D' => 'community'], ['A'], 1],
                    ['true_false', 'Culture is the total way of life of a people.', null, ['true'], 1],
                    ['short', 'Name one Nigerian language.', null, ['Hausa', 'Yoruba', 'Igbo', 'Efik', 'Tiv', 'Ijaw', 'Kanuri', 'Fulfulde', 'Ibibio', 'Edo'], 1],
                ]],
            ],
        ],
        'BTE' => [
            'assignments' => [
                ['Workshop safety', 'List ten safety rules in the technical workshop and explain why each is important.'],
            ],
            'quizzes' => [
                ['Drawing instruments quiz', [
                    ['single', 'Which instrument is used to draw circles?', ['A' => 'T-square', 'B' => 'Compass', 'C' => 'Set square', 'D' => 'Protractor'], ['B'], 1],
                    ['true_false', 'A 2H pencil is softer than an HB pencil.', null, ['false'], 1],
                    ['single', 'Wood obtained from conifers is called…', ['A' => 'hardwood', 'B' => 'softwood', 'C' => 'plywood', 'D' => 'chipboard'], ['B'], 1],
                ]],
            ],
        ],
        'GOV' => [
            'assignments' => [
                ['Arms of government', "Explain the functions of the legislature, executive and judiciary in Nigeria.\nGive one example of how each checks the others."],
            ],
            'quizzes' => [
                ['Democracy quiz', [
                    ['single', 'Government of the people, by the people, for the people describes…', ['A' => 'monarchy', 'B' => 'democracy', 'C' => 'oligarchy', 'D' => 'theocracy'], ['B'], 1],
                    ['true_false', 'Nigeria operates a federal system of government.', null, ['true'], 1],
                    ['short', 'How many states are in Nigeria?', null, ['36'], 1],
                ]],
            ],
        ],
    ];

    /** Short typed answers students hand in alongside or instead of their uploads. */
    private const ANSWERS = [
        'Please find my work attached. I did all the questions.',
        "1. 3/4\n2. 0.6\n3. 7/10\n4. 1 1/2\n5. 0.875\nI found number 4 difficult.",
        'I have done the work in my exercise book and taken pictures of the pages.',
        'Here is my answer. I used the method you showed us in class.',
        "My essay is attached. I wrote about the day my family travelled to Kano for my cousin's wedding.",
        'Done. Sorry it is a little late, there was no light at home yesterday.',
    ];

    private const FEEDBACK = [
        'Excellent work. Well done!', 'Good effort. Keep it up.', 'Neat and well presented.',
        'Show your working next time.', 'Good, but check your spelling.', 'Some answers are incomplete. See me.',
    ];

    /** @var list<string> */
    private array $codes;

    public function __construct(private int $schoolId)
    {
        $this->codes = array_keys(self::BANK);
    }

    public function build(Term $term, ?int $busyTeacherId): void
    {
        mt_srand(7070 + $this->schoolId);
        Storage::disk(StoredFiles::DISK)->deleteDirectory("schools/{$this->schoolId}/submissions");

        $classSubjects = ClassSubject::query()
            ->with(['subject', 'classArm.level', 'teacher'])
            ->whereHas('subject', fn ($query) => $query->whereIn('code', $this->codes))
            ->get()
            ->filter(fn (ClassSubject $classSubject) => in_array($classSubject->classArm->level->section, [ClassSection::Junior, ClassSection::Senior], true)
                || ($classSubject->classArm->level->section === ClassSection::Primary && $classSubject->classArm->level->position >= 7));

        $students = Enrolment::query()->where('academic_session_id', $term->academic_session_id)->get(['student_id', 'class_arm_id'])
            ->groupBy('class_arm_id')->map(fn (Collection $rows) => $rows->pluck('student_id')->all());

        foreach ($classSubjects as $classSubject) {
            $class = $students[$classSubject->class_arm_id] ?? [];

            if ($class === [] || $classSubject->teacher === null) {
                continue;
            }

            $bank = self::BANK[$classSubject->subject->code];
            $userId = $classSubject->teacher->user_id;
            $pick = $classSubject->id;

            [$title, $instructions] = $bank['assignments'][$pick % count($bank['assignments'])];
            $this->assignment($classSubject, $term, $userId, $title, $instructions, now()->subDays(9)->setTime(8, 0), $class, handedIn: 85, marked: 80);

            if (count($bank['assignments']) > 1) {
                [$title, $instructions] = $bank['assignments'][($pick + 1) % count($bank['assignments'])];
                $this->assignment($classSubject, $term, $userId, $title, $instructions, now()->addDays(4)->setTime(8, 0), $class, handedIn: 35, marked: 0);
            }

            // The demo teacher's own classes: a pile of fresh work to mark, with uploaded pages.
            if ($busyTeacherId !== null && $classSubject->teacher_id === $busyTeacherId && count($bank['assignments']) > 2) {
                [$title, $instructions] = $bank['assignments'][($pick + 2) % count($bank['assignments'])];
                $this->assignment($classSubject, $term, $userId, $title, $instructions, now()->subDays(2)->setTime(8, 0), $class, handedIn: 90, marked: 0, uploads: 70);
            }

            [$quizTitle, $questions] = $bank['quizzes'][$pick % count($bank['quizzes'])];
            $this->quiz($classSubject, $term, $userId, $quizTitle, QuizKind::Quiz, $questions, now()->subDays(20), now()->subDays(13), $class, took: 92);

            if (count($bank['quizzes']) > 1) {
                [$quizTitle, $questions] = $bank['quizzes'][($pick + 1) % count($bank['quizzes'])];
                $this->quiz($classSubject, $term, $userId, $quizTitle, QuizKind::Test, $questions, now()->subDay(), now()->addDays(5), $class, took: 30);
            }
        }
    }

    /**
     * @param  list<int>  $class
     */
    private function assignment(ClassSubject $classSubject, Term $term, ?int $userId, string $title, string $instructions, Carbon $due, array $class, int $handedIn, int $marked, int $uploads = 25): void
    {
        $assignment = Assignment::query()->create([
            'class_subject_id' => $classSubject->id, 'term_id' => $term->id, 'created_by' => $userId,
            'title' => $title, 'instructions' => $instructions, 'due_at' => $due, 'max_score' => 10,
            'status' => ClassworkStatus::Published,
        ]);
        $assignment->forceFill(['created_at' => $due->copy()->subWeek(), 'updated_at' => $due->copy()->subWeek()])->saveQuietly();

        $rows = [];

        foreach ($class as $studentId) {
            if (mt_rand(1, 100) > $handedIn) {
                continue;
            }

            $late = $due->isPast() && mt_rand(1, 100) <= 10;
            $submittedAt = $late ? $due->copy()->addHours(mt_rand(3, 40)) : $due->copy()->subHours(mt_rand(2, 120));
            $submittedAt = $submittedAt->isFuture() ? now()->subHours(mt_rand(1, 20)) : $submittedAt;
            $isMarked = $due->isPast() && mt_rand(1, 100) <= $marked;
            $files = mt_rand(1, 100) <= $uploads ? $this->files($assignment, $studentId, $title) : null;

            $rows[] = [
                'school_id' => $this->schoolId,
                'assignment_id' => $assignment->id,
                'student_id' => $studentId,
                'body' => $files && mt_rand(1, 2) === 1 ? null : self::ANSWERS[mt_rand(0, count(self::ANSWERS) - 1)],
                'files' => $files ? json_encode($files) : null,
                'submitted_at' => $submittedAt,
                'score' => $isMarked ? mt_rand(8, 20) / 2 : null,
                'feedback' => $isMarked ? self::FEEDBACK[mt_rand(0, count(self::FEEDBACK) - 1)] : null,
                'marked_by' => $isMarked ? $userId : null,
                'marked_at' => $isMarked ? $due->copy()->addDays(mt_rand(1, 3)) : null,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ];
        }

        DB::table('assignment_submissions')->insert($rows);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: array<string, string>|null, 3: list<string>, 4: int}>  $questions
     * @param  list<int>  $class
     */
    private function quiz(ClassSubject $classSubject, Term $term, ?int $userId, string $title, QuizKind $kind, array $questions, Carbon $opens, Carbon $closes, array $class, int $took): void
    {
        $quiz = Quiz::query()->create([
            'class_subject_id' => $classSubject->id, 'term_id' => $term->id, 'created_by' => $userId,
            'title' => $title, 'instructions' => 'Answer every question. Your answers save as you go.', 'kind' => $kind,
            'duration_minutes' => $kind === QuizKind::Quiz ? 15 : 30, 'opens_at' => $opens, 'closes_at' => $closes,
            'shuffle_questions' => $kind !== QuizKind::Quiz, 'show_answers' => true, 'status' => ClassworkStatus::Published,
        ]);

        $saved = collect($questions)->map(fn (array $question, int $position) => QuizQuestion::query()->create([
            'quiz_id' => $quiz->id, 'type' => $question[0], 'prompt' => $question[1],
            'options' => $question[2] === null ? null : collect($question[2])->map(fn (string $text, string $key) => ['key' => $key, 'text' => $text])->values()->all(),
            'answer' => $question[3], 'marks' => $question[4], 'position' => $position + 1,
        ]));
        $maxScore = $saved->sum('marks');
        $rows = [];

        foreach ($class as $studentId) {
            if (mt_rand(1, 100) > $took) {
                continue;
            }

            $ability = mt_rand(45, 98);
            $answers = [];
            $score = 0;

            foreach ($saved as $question) {
                $right = mt_rand(1, 100) <= $ability;
                $answers[$question->id] = $this->response($question, $right);
                $score += $question->type->isCorrect($question->answer, $answers[$question->id]) ? $question->marks : 0;
            }

            $started = $opens->copy()->addHours(mt_rand(1, max(2, (int) $opens->diffInHours(min($closes, now())) - 1)));
            $rows[] = [
                'school_id' => $this->schoolId, 'quiz_id' => $quiz->id, 'student_id' => $studentId,
                'started_at' => $started, 'submitted_at' => $started->copy()->addMinutes(mt_rand(4, 14)),
                'answers' => json_encode($answers), 'score' => $score, 'max_score' => $maxScore,
                'created_at' => $started, 'updated_at' => $started,
            ];
        }

        DB::table('quiz_attempts')->insert($rows);
    }

    private function response(QuizQuestion $question, bool $right): mixed
    {
        $keys = collect($question->options ?? [])->pluck('key')->all();

        return match ($question->type) {
            QuestionType::SingleChoice => $right ? $question->answer[0] : collect($keys)->reject(fn ($key) => $key === $question->answer[0])->random(),
            QuestionType::MultipleChoice => $right ? $question->answer : [collect($keys)->random()],
            QuestionType::TrueFalse => $right ? $question->answer[0] : ($question->answer[0] === 'true' ? 'false' : 'true'),
            QuestionType::ShortAnswer => $right ? $question->answer[0] : 'not sure',
        };
    }

    /**
     * Photos of exercise-book pages, or a typed-up PDF, like students really hand in.
     *
     * @return list<array{path: string, name: string, size: int, mime: string}>
     */
    private function files(Assignment $assignment, int $studentId, string $title): array
    {
        $directory = "schools/{$this->schoolId}/submissions/{$assignment->ulid}";
        $files = [];

        if (mt_rand(1, 3) === 1 || ! function_exists('imagepng')) {
            $contents = DemoFiles::pdf($title, ['Name: student '.$studentId, 'Class work', '', ...DemoFiles::lines($title, 18)]);
            $files[] = $this->put($directory, 'pdf', $contents, Str::slug($title).'.pdf', 'application/pdf');
        } else {
            foreach (range(1, mt_rand(1, 2)) as $page) {
                $contents = DemoFiles::page($title, $page, DemoFiles::lines($title, 14));
                $files[] = $this->put($directory, 'png', $contents, 'page-'.$page.'.png', 'image/png');
            }
        }

        return $files;
    }

    /**
     * @return array{path: string, name: string, size: int, mime: string}
     */
    private function put(string $directory, string $extension, string $contents, string $name, string $mime): array
    {
        $path = $directory.'/'.Str::ulid()->toBase32().'.'.$extension;
        Storage::disk(StoredFiles::DISK)->put($path, $contents);

        return ['path' => $path, 'name' => $name, 'size' => strlen($contents), 'mime' => $mime];
    }
}
