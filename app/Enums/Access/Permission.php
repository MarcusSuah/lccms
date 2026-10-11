<?php

namespace App\Enums\Access;

use App\Enums\Concerns\HasOptions;

/**
 * Granular permissions. Roles bundle these in the RolesAndPermissionsSeeder;
 * administrators may adjust role grants without code changes.
 */
enum Permission: string
{
    use HasOptions;

    case GeoNational = 'geo.national';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';

    case GeographyView = 'geography.view';
    case GeographyManage = 'geography.manage';
    case GeographyImport = 'geography.import';
    case GeographyReassign = 'geography.reassign';
    case GeographyApproveReassignment = 'geography.approve-reassignment';

    case CampaignsView = 'campaigns.view';
    case CampaignsManage = 'campaigns.manage';
    case AssignmentsManage = 'assignments.manage';

    case CensusView = 'census.view';
    case CensusViewPersonalData = 'census.view-personal-data';
    case CensusEnumerate = 'census.enumerate';
    case CensusVerify = 'census.verify';
    case CensusApprove = 'census.approve';
    case CensusCorrect = 'census.correct';
    case CensusApproveCorrection = 'census.approve-correction';
    case CensusReviewDuplicates = 'census.review-duplicates';

    case SyncUse = 'sync.use';
    case SyncResolveConflicts = 'sync.resolve-conflicts';
    case DevicesManage = 'devices.manage';

    case FacilitiesView = 'facilities.view';
    case FacilitiesManage = 'facilities.manage';
    case FacilitiesInspect = 'facilities.inspect';
    case FacilitiesVerify = 'facilities.verify';

    case LeadershipView = 'leadership.view';
    case LeadershipManage = 'leadership.manage';
    case LeadershipVerify = 'leadership.verify';

    case InfrastructureView = 'infrastructure.view';
    case InfrastructureManage = 'infrastructure.manage';

    case ContentManage = 'content.manage';
    case ContentPublish = 'content.publish';
    case MediaApprove = 'media.approve';
    case ContactMessagesView = 'contact-messages.view';

    case ComplaintsView = 'complaints.view';
    case ComplaintsManage = 'complaints.manage';
    case ComplaintsAssign = 'complaints.assign';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ReportsExportPersonalData = 'reports.export-personal-data';

    public function label(): string
    {
        return str($this->value)->replace(['.', '-'], ' ')->ucfirst()->toString();
    }
}
