<?php


namespace Stevebauman\Translation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @package Stevebauman\Translation\Jobs
 */
class Actualize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 5;

    /**
     * @var int
     */
    public $timeout = 20;

    /**
     * @var array
     */
    public $ids = [];

    /**
     * @param array $ids
     */
    public function __construct(array $ids = [])
    {
        $this->ids = $ids;
    }

    public function handle()
    {
        DB::beginTransaction();

        foreach ($this->ids as $id) {
            DB::table('translations')
                ->where('id', $id)
                ->update(['is_relevant' => true]);
        }

        DB::commit();
    }
}