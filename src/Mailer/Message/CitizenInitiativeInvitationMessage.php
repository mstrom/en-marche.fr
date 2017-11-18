<?php

namespace AppBundle\Mailer\Message;

use AppBundle\Entity\CitizenInitiative;
use AppBundle\Entity\EventInvite;
use Ramsey\Uuid\Uuid;

final class CitizenInitiativeInvitationMessage extends Message
{
    public static function createFromInvite(EventInvite $invite, CitizenInitiative $initiative, string $eventUrl): self
    {
        $message = new self(
            Uuid::uuid4(),
            $invite->getEmail(),
            $invite->getFullName(),
            self::getTemplateVars(
                $invite->getFirstName(),
                $invite->getMessage(),
                $initiative->getName(),
                $eventUrl
            ),
            [],
            $invite->getEmail()
        );

        foreach ($invite->getGuests() as $guest) {
            $message->addCC($guest);
        }

        return $message;
    }

    private static function getTemplateVars(
        string $senderFirstName,
        string $senderMessage,
        string $eventName,
        string $eventUrl
    ): array {
        return [
            'sender_firstname' => self::escape($senderFirstName),
            'sender_message' => self::escape($senderMessage),
            'event_name' => self::escape($eventName),
            'event_slug' => $eventUrl,
        ];
    }
}
