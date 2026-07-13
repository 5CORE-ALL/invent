<?php

namespace App\Services;

use App\Models\AmazonCampaignLink;

/**
 * Campaign-name linking for Amazon SP campaigns — mirrors {@see LmpSkuLinkService}.
 * Links are stored as a bidirectional, fully-connected mesh of edges; a "group" is the
 * connected component reachable from a campaign. Linked campaigns will share keywords when
 * pushed together.
 */
class AmazonCampaignLinkService
{
    /** Eloquent model backing the links (overridable by subclasses, e.g. negative-keyword links). */
    protected string $modelClass = AmazonCampaignLink::class;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    protected function model(): string
    {
        return $this->modelClass;
    }

    public function normalize(string $campaign): string
    {
        return strtoupper(trim($campaign));
    }

    public function link(string $campaign, string $linkedCampaign, ?string $user = null): void
    {
        $campaign = trim($campaign);
        $linkedCampaign = trim($linkedCampaign);

        if ($campaign === '' || $linkedCampaign === '') {
            return;
        }

        if ($this->normalize($campaign) === $this->normalize($linkedCampaign)) {
            return;
        }

        foreach ([[$campaign, $linkedCampaign], [$linkedCampaign, $campaign]] as [$from, $to]) {
            ($this->model())::updateOrCreate(
                [
                    'campaign_norm' => $this->normalize($from),
                    'linked_campaign_norm' => $this->normalize($to),
                ],
                [
                    'campaign' => $from,
                    'linked_campaign' => $to,
                    'updated_by' => $user,
                ]
            );
        }
    }

    public function unlink(string $campaign, string $linkedCampaign): void
    {
        $campaign = trim($campaign);
        $linkedCampaign = trim($linkedCampaign);

        if ($campaign === '' || $linkedCampaign === '') {
            return;
        }

        $leftNorm = $this->normalize($campaign);
        $rightNorm = $this->normalize($linkedCampaign);

        ($this->model())::query()
            ->where(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('campaign_norm', $leftNorm)->where('linked_campaign_norm', $rightNorm);
            })
            ->orWhere(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('campaign_norm', $rightNorm)->where('linked_campaign_norm', $leftNorm);
            })
            ->delete();
    }

    /**
     * Fully detach a campaign from a linked group, then re-link the remaining members so they
     * stay grouped together (removing a hub could otherwise split the rest apart).
     *
     * @param  list<string>  $groupMembers
     */
    public function unlinkFromGroup(string $campaignToRemove, array $groupMembers, ?string $user = null): void
    {
        $removeNorm = $this->normalize($campaignToRemove);
        if ($removeNorm === '') {
            return;
        }

        $memberNorms = [];
        $remaining = [];
        foreach ($groupMembers as $member) {
            $display = trim((string) $member);
            $norm = $this->normalize($display);
            if ($norm === '' || $norm === $removeNorm) {
                continue;
            }
            $memberNorms[$norm] = $norm;
            $remaining[$norm] = $display;
        }

        $memberNorms = array_values($memberNorms);
        if ($memberNorms !== []) {
            ($this->model())::query()
                ->where(function ($query) use ($removeNorm, $memberNorms) {
                    $query->where('campaign_norm', $removeNorm)
                        ->whereIn('linked_campaign_norm', $memberNorms);
                })
                ->orWhere(function ($query) use ($removeNorm, $memberNorms) {
                    $query->where('linked_campaign_norm', $removeNorm)
                        ->whereIn('campaign_norm', $memberNorms);
                })
                ->delete();
        }

        $this->syncFullyConnectedGroup(array_values($remaining), $user);
    }

    /**
     * @param  list<string>  $campaigns
     */
    public function syncFullyConnectedGroup(array $campaigns, ?string $user = null): void
    {
        $campaigns = array_values(array_unique(array_filter(array_map('trim', $campaigns))));

        for ($i = 0, $count = count($campaigns); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->link($campaigns[$i], $campaigns[$j], $user);
            }
        }
    }

    /**
     * Connected-component group for a single campaign (includes the campaign itself).
     *
     * @return list<string>
     */
    public function groupContaining(string $campaign): array
    {
        $campaign = trim($campaign);
        if ($campaign === '') {
            return [];
        }

        $map = $this->groupsMap();

        return $map[$this->normalize($campaign)] ?? [$campaign];
    }

    /**
     * Build norm => connected-group map over ALL stored links via union-find.
     *
     * @return array<string, list<string>>
     */
    public function groupsMap(): array
    {
        $parent = [];
        $display = [];

        $find = function (string $x) use (&$parent, &$find): string {
            if (! isset($parent[$x])) {
                $parent[$x] = $x;
            }
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };

        ($this->model())::query()
            ->select(['campaign', 'linked_campaign', 'campaign_norm', 'linked_campaign_norm'])
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$parent, &$display, $find) {
                foreach ($rows as $row) {
                    $a = (string) $row->campaign_norm;
                    $b = (string) $row->linked_campaign_norm;
                    if ($a === '' || $b === '') {
                        continue;
                    }
                    $display[$a] = (string) $row->campaign;
                    $display[$b] = (string) $row->linked_campaign;
                    $ra = $find($a);
                    $rb = $find($b);
                    if ($ra !== $rb) {
                        $parent[$ra] = $rb;
                    }
                }
            });

        $groupsByRoot = [];
        foreach (array_keys($parent) as $norm) {
            $root = $find($norm);
            $groupsByRoot[$root][] = $display[$norm] ?? $norm;
        }

        $map = [];
        foreach ($groupsByRoot as $members) {
            $group = array_values(array_unique(array_filter(array_map('trim', $members))));
            if ($group === []) {
                continue;
            }
            sort($group, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($group as $member) {
                $map[$this->normalize($member)] = $group;
            }
        }

        return $map;
    }
}
