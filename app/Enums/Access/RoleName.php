<?php

namespace App\Enums\Access;

use App\Enums\Concerns\HasOptions;

enum RoleName: string
{
    use HasOptions;

    case SystemSuperAdministrator = 'system-super-administrator';
    case NationalCensusAdministrator = 'national-census-administrator';
    case CountyAdministrator = 'county-administrator';
    case DistrictAdministrator = 'district-administrator';
    case TownOfficer = 'town-officer';
    case ClanAdministrator = 'clan-administrator';
    case CommunityAdministrator = 'community-administrator';
    case BlockLeader = 'block-leader';
    case CensusSupervisor = 'census-supervisor';
    case CensusEnumerator = 'census-enumerator';
    case FacilityInspector = 'facility-inspector';
    case ComplaintOfficer = 'complaint-officer';
    case ContentEditor = 'content-editor';
    case ReportViewer = 'report-viewer';
    case PublicVisitor = 'public-visitor';

    public function label(): string
    {
        return match ($this) {
            self::SystemSuperAdministrator => 'System Super Administrator',
            self::NationalCensusAdministrator => 'National Census Administrator',
            self::CountyAdministrator => 'County Administrator',
            self::DistrictAdministrator => 'District Administrator',
            self::TownOfficer => 'Town Officer',
            self::ClanAdministrator => 'Clan Administrator',
            self::CommunityAdministrator => 'Community Administrator',
            self::BlockLeader => 'Block Leader',
            self::CensusSupervisor => 'Census Supervisor',
            self::CensusEnumerator => 'Census Enumerator',
            self::FacilityInspector => 'Facility Inspector',
            self::ComplaintOfficer => 'Complaint Officer',
            self::ContentEditor => 'Content Editor',
            self::ReportViewer => 'Report Viewer',
            self::PublicVisitor => 'Public Visitor',
        };
    }

    /**
     * Roles that must protect their account with two-factor authentication or a passkey.
     */
    public function requiresStrongAuthentication(): bool
    {
        return in_array($this, [self::SystemSuperAdministrator, self::NationalCensusAdministrator], true);
    }

    /**
     * Roles that only a system super administrator may grant.
     */
    public function isPrivileged(): bool
    {
        return $this === self::SystemSuperAdministrator;
    }
}
