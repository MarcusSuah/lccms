<?php

namespace Database\Seeders;

use App\Enums\Access\Permission as P;
use App\Enums\Access\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotently creates every permission and the initial role bundles.
 * Geographic reach is not granted here: it comes from user_geo_scopes,
 * except for roles holding geo.national.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (self::grants() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions(
                array_map(fn (P $permission): string => $permission->value, $permissions),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, list<P>>
     */
    public static function grants(): array
    {
        $areaAdministration = [
            P::UsersView, P::UsersManage,
            P::GeographyView, P::GeographyManage, P::GeographyReassign,
            P::CampaignsView, P::AssignmentsManage,
            P::CensusView, P::CensusViewPersonalData, P::CensusVerify, P::CensusCorrect, P::CensusReviewDuplicates,
            P::FacilitiesView, P::FacilitiesManage, P::FacilitiesVerify,
            P::LeadershipView, P::LeadershipManage, P::LeadershipVerify,
            P::InfrastructureView, P::InfrastructureManage,
            P::ComplaintsView,
            P::ReportsView, P::ReportsExport,
        ];

        $seniorAreaAdministration = [
            ...$areaAdministration,
            P::GeographyImport, P::CensusApprove, P::CensusApproveCorrection, P::ComplaintsAssign, P::AuditView,
            P::DevicesManage, P::SyncResolveConflicts,
        ];

        return [
            RoleName::SystemSuperAdministrator->value => P::cases(),
            RoleName::NationalCensusAdministrator->value => array_values(array_filter(
                P::cases(),
                fn (P $permission): bool => $permission !== P::SettingsManage,
            )),
            RoleName::CountyAdministrator->value => $seniorAreaAdministration,
            RoleName::DistrictAdministrator->value => $seniorAreaAdministration,
            RoleName::ClanAdministrator->value => $areaAdministration,
            RoleName::TownOfficer->value => $areaAdministration,
            RoleName::CommunityAdministrator->value => [
                P::GeographyView, P::CensusView,
                P::FacilitiesView, P::FacilitiesManage,
                P::LeadershipView, P::LeadershipManage,
                P::InfrastructureView, P::InfrastructureManage,
                P::ComplaintsView, P::ReportsView,
            ],
            RoleName::BlockLeader->value => [
                P::GeographyView, P::CensusView, P::FacilitiesView, P::LeadershipView,
                P::InfrastructureView, P::ReportsView,
            ],
            RoleName::CensusSupervisor->value => [
                P::GeographyView, P::CampaignsView, P::AssignmentsManage,
                P::CensusView, P::CensusViewPersonalData, P::CensusVerify, P::CensusCorrect, P::CensusReviewDuplicates,
                P::SyncResolveConflicts, P::DevicesManage, P::ReportsView,
            ],
            RoleName::CensusEnumerator->value => [
                P::GeographyView, P::CampaignsView, P::CensusEnumerate, P::SyncUse,
            ],
            RoleName::FacilityInspector->value => [
                P::GeographyView, P::FacilitiesView, P::FacilitiesManage, P::FacilitiesInspect,
                P::InfrastructureView, P::InfrastructureManage,
            ],
            RoleName::ComplaintOfficer->value => [
                P::GeographyView, P::ComplaintsView, P::ComplaintsManage, P::ComplaintsAssign, P::ContactMessagesView,
            ],
            RoleName::ContentEditor->value => [
                P::GeographyView, P::ContentManage, P::ContentPublish, P::MediaApprove, P::ContactMessagesView,
            ],
            RoleName::ReportViewer->value => [
                P::GeographyView, P::CensusView, P::FacilitiesView, P::LeadershipView, P::InfrastructureView,
                P::ReportsView, P::ReportsExport,
            ],
            RoleName::PublicVisitor->value => [],
        ];
    }
}
