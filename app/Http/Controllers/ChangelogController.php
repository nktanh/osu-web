<?php

/**
 *    Copyright 2015-2017 ppy Pty. Ltd.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace App\Http\Controllers;

use App\Models\Build;
use App\Models\BuildPropagationHistory;
use App\Models\Changelog;
use App\Models\ChangelogEntry;
use Cache;
use Carbon\Carbon;

class ChangelogController extends Controller
{
    protected $section = 'home';
    protected $actionPrefix = 'changelog-';

    public function index()
    {
        $from = (max(
            optional(Changelog::default()->first())->date,
            optional(ChangelogEntry::default()->first())->created_at
        ) ?? Carbon::now())->subWeeks(config('osu.changelog.recent_weeks'));

        $legacyChangelogs = Changelog::default()
            ->with('user')
            ->where('date', '>', $from)
            ->get()
            ->map(function ($item) {
                return ChangelogEntry::convertLegacy($item);
            });

        $changelogs = ChangelogEntry::default()
            ->with('githubUser.user')
            ->where('created_at', '>', $from)
            ->get()
            ->concat($legacyChangelogs)
            ->sortByDesc('created_at')
            ->groupBy(function ($item) {
                return i18n_date($item->created_at);
            });

        $this->getBuilds();

        $buildHistory = Cache::remember('build_propagation_history_global', config('osu.changelog.build_history_interval'), function () {
            return BuildPropagationHistory::changelog(null, config('osu.changelog.chart_days'))->get();
        });

        $chartOrder = collect([$this->featuredBuild])->merge($this->builds)->map(function ($el) {
            return $el->updateStream->pretty_name;
        });

        return view('changelog.index', compact('changelogs', 'buildHistory', 'chartOrder'));
    }

    public function github()
    {
        $token = config('osu.changelog.github_token');

        list($algo, $signature) = explode('=', request()->header('X-Hub-Signature'));
        $hash = hash_hmac($algo, request()->getContent(), $token);

        if (!hash_equals((string) $hash, (string) $signature)) {
            abort(403);
        }

        ChangelogEntry::importFromGithub(request()->json()->all());

        return [];
    }

    public function show($buildId)
    {
        $activeBuild = Build::default()
            ->with('updateStream')
            ->where('version', $buildId)
            ->firstOrFail();

        $legacyChangelogs = $activeBuild->changelogs()
            ->default()
            ->with('user')
            ->visibleOnBuilds()
            ->get()
            ->map(function ($item) {
                return ChangelogEntry::convertLegacy($item);
            });

        $changelogs = $activeBuild->changelogEntries()
            ->default()
            ->with('githubUser')
            ->get()
            ->concat($legacyChangelogs);

        if (count($changelogs) === 0) {
            $changelogs = [ChangelogEntry::placeholder()];
        }

        $this->getBuilds();

        $buildHistory = Cache::remember("build_propagation_history_{$activeBuild->stream_id}", config('osu.changelog.build_history_interval'), function () use ($activeBuild) {
            return BuildPropagationHistory::changelog($activeBuild->stream_id, config('osu.changelog.chart_days'))->get();
        });

        $chartOrder = $buildHistory
            ->unique('label')
            ->pluck('label')
            ->sortByDesc(function ($label) {
                $parts = explode('.', $label);
                if (count($parts) >= 1 && strlen($parts[0]) >= 8) {
                    $date = substr($parts[0], 0, 8);
                } elseif (count($parts) >= 2 && strlen($parts[0]) === 4 && strlen($parts[1]) >= 3 && strlen($parts[1]) <= 4) {
                    $date = $parts[0].str_pad($parts[1], 4, '0', STR_PAD_LEFT);
                }

                return $date ?? null;
            })->values();

        return view('changelog.show', compact('changelogs', 'activeBuild', 'buildHistory', 'chartOrder'));
    }

    private function getBuilds()
    {
        $this->builds = Build::latestByStream(config('osu.changelog.update_streams'))
            ->get();

        $this->featuredBuild = null;

        foreach ($this->builds as $index => $build) {
            if ($build->stream_id === config('osu.changelog.featured_stream')) {
                $this->featuredBuild = $build;
                unset($this->builds[$index]);
            }
        }

        view()->share('builds', $this->builds);
        view()->share('featuredBuild', $this->featuredBuild);
    }
}
