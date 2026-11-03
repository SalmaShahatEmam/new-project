<?php

namespace App\Repositories\Contracts;

use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use Illuminate\Database\Eloquent\Collection;

interface SiteContract extends BaseContract
{
    public function createSite(array $attributes, array $userIds): Site;

    public function updateSite(Site $site, array $attributes, ?array $userIds): Site;

    public function getSiteDetails(Site $site, ?string $date = null, array $duration = []): array;

    public function getReportDetails(SiteReport $report): SiteReport;

    public function deleteSiteReport(SiteReport $report): void;

    public function deleteSiteReportsByFilters(array $filters): int;

    public function searchSites(
        array $filters = [],
        bool $applyOrder = true,
        bool $page = true,
        int $limit = self::LIMIT,
        string $orderBy = self::ORDER_BY,
        string $orderDir = self::ORDER_DIR
    ): mixed;

    public function deleteSite(Site $site): void;

    public function updateSiteReports( array $attributes, int $userId);

    public function expandSiteDuration(Site $site, int $durationInMonths): Site;

    public function syncSiteUsers(int $siteId, array $userIds): Site;

    public function removeSiteUser(int $siteId, int $userId): Site;

    public function updateSiteStatus(Site $site, string $status): Site;

    public function addSiteReports(array $attributes, int $userId): Collection;

    public function addSiteProblem(Site $site, array $attributes, array $files): SiteProblem;

    public function getSiteProblems(Site $site): Collection;

    public function updateSiteProblem(SiteProblem $problem, array $attributes, array $files = []): SiteProblem;

    public function deleteSiteProblem(SiteProblem $problem): void;

    public function getReportsAndProblemsByDate(array $attributes): array;
    public function getSitesWithReports(array $filters = []): mixed;
}
