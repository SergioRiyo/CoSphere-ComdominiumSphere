<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Poll;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\PollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Module5DemoSeeder extends Seeder
{
    public function run(): void
    {
        // The prerequisite seeder verifies the isolated database and temporary password.
        $this->call(Module3DemoSeeder::class);

        DB::transaction(function (): void {
            $admin = User::where('email', 'admin@m3-demo.cosphere.test')->firstOrFail();
            $announcements = app(AnnouncementService::class);
            $polls = app(PollService::class);

            foreach ([
                ['Demonstração: manutenção da piscina', 'A piscina ficará fechada para manutenção na próxima semana.', true],
                ['Demonstração: reunião de moradores', 'Rascunho para revisão antes da publicação.', false],
            ] as [$title, $content, $publish]) {
                $announcement = Announcement::where('author_id', $admin->id)->where('title', $title)->first();
                if ($announcement === null) {
                    $announcement = $announcements->save($admin, compact('title', 'content'));
                    if ($publish) {
                        $announcements->publish($admin, $announcement);
                    }
                }
            }

            foreach ([
                ['Demonstração: qual melhoria priorizar na área comum?', ['Iluminação', 'Jardinagem', 'Abster-se'], true],
                ['Demonstração: qual horário para a reunião?', ['Manhã', 'Noite'], false],
            ] as [$question, $options, $publish]) {
                $poll = Poll::where('author_id', $admin->id)->where('question', $question)->first();
                if ($poll === null) {
                    $poll = $polls->save($admin, compact('question', 'options'));
                    if ($publish) {
                        $polls->publish($admin, $poll);
                    }
                }
            }
        });
    }
}
