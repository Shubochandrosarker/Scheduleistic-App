<?php

namespace App\Console\Commands;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\PostTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('posts:dispatch-due')]
#[Description('Dispatch publishing jobs for every scheduled post whose time has come.')]
class DispatchDuePosts extends Command
{
    public function handle(): int
    {
        $due = Post::query()
            ->where('status', Post::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        $dispatched = 0;

        foreach ($due as $post) {
            // Claim the post atomically so overlapping runs can't double-dispatch.
            $claimed = DB::transaction(function () use ($post) {
                $fresh = Post::whereKey($post->id)
                    ->where('status', Post::STATUS_SCHEDULED)
                    ->lockForUpdate()
                    ->first();

                if (! $fresh) {
                    return false;
                }

                $fresh->update(['status' => Post::STATUS_PUBLISHING]);

                return true;
            });

            if (! $claimed) {
                continue;
            }

            foreach ($post->targets()->where('status', PostTarget::STATUS_PENDING)->get() as $target) {
                PublishPostJob::dispatch($target);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} publish job(s) for {$due->count()} due post(s).");

        return self::SUCCESS;
    }
}
