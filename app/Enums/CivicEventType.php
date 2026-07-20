<?php

namespace App\Enums;

enum CivicEventType: string
{
    case TownHall = 'town_hall';
    case BallotMeasureDrive = 'ballot_measure_drive';
    case CommunityMeeting = 'community_meeting';
    case Rally = 'rally';
    case Workshop = 'workshop';
    case Fundraiser = 'fundraiser';

    public function label(): string
    {
        return match ($this) {
            self::TownHall => 'Town Hall',
            self::BallotMeasureDrive => 'Ballot Measure Drive',
            self::CommunityMeeting => 'Community Meeting',
            self::Rally => 'Rally',
            self::Workshop => 'Workshop',
            self::Fundraiser => 'Fundraiser',
        };
    }
}
