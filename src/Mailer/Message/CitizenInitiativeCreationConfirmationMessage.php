<?php

namespace AppBundle\Mailer\Message;

use AppBundle\CitizenInitiative\CitizenInitiativeCreatedEvent;
use Ramsey\Uuid\Uuid;

final class CitizenInitiativeCreationConfirmationMessage extends Message
{
    public static function create(CitizenInitiativeCreatedEvent $event): self
    {
        $author = $event->getAuthor();
        $initiative = $event->getCitizenInitiative();

        return new self(
            Uuid::uuid4(),
            $author->getEmailAddress(),
            $author->getFirstName(),
            self::getTemplateVars($initiative->getName()),
            self::getRecipientVars($author->getFirstName())
        );
    }

    private static function getTemplateVars(string $initiativeName): array
    {
        return [
            'IC_name' => self::escape($initiativeName),
        ];
    }

    private static function getRecipientVars(string $firstName): array
    {
        return [
            'prenom' => self::escape($firstName),
        ];
    }
}
