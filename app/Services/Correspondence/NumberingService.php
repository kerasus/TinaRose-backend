<?php

namespace App\Services\Correspondence;

use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class NumberingService
{
    public function issue(): string
    {
        $year = (int) Jalalian::now()->getYear();

        $row = DB::table('correspondence_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            DB::table('correspondence_sequences')->insert([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('correspondence_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
        }

        $next = ((int) $row->last_number) + 1;

        DB::table('correspondence_sequences')
            ->where('year', $year)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%d/%06d', $year, $next);
    }
}
