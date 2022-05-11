<?php

namespace Galilee\ImportExportBundle\Helper;

use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Note;

class PimHelper
{
    const STATUS_IMPORT_SOCODA_INIT = '1';
    const STATUS_IMPORT_SOCODA_SYNC = '2';
    const STATUS_IMPORT_SOCODA_TO_SYNC = '3';
    const STATUS_IMPORT_SOCODA_NO_SYNC = '4';

    const MESSAGES = [
        self::STATUS_IMPORT_SOCODA_INIT => 'Demande de Synchronisation avec le PIM SOCODA (1 : Initialisé)',
        self::STATUS_IMPORT_SOCODA_SYNC => 'Synchronisation avec le PIM SOCODA réalisée (2 : synchronisé)',
        self::STATUS_IMPORT_SOCODA_TO_SYNC => 'Demande de Synchronisation avec le PIM SOCODA  (3 : A synchroniser)',
        self::STATUS_IMPORT_SOCODA_NO_SYNC => 'Synchronisation avec le PIM SOCODA désactivée (4 : Ne pas synchroniser)',
    ];

    const NOTE_TYPE_IMPORT_PIM = 'Import Pim Socoda';
    const NOTE_TYPE_IMPORT_ADHERENT = 'Import adhérent';
    const NOTE_TYPE_MANUAL = 'Action manuelle';

    public static function addStatusNote(
        ElementInterface $object,
        int $userId,
        string $type,
        string $newStatus
    )
    {
        if (isset(self::MESSAGES[$newStatus])) {
            self::createNote(
                $object,
                $userId,
                $type,
                self::MESSAGES[$newStatus]
            );
        }
    }

    public static function createNote(
        ElementInterface $object,
        int $userId,
        string $type,
        string $title,
        array $data = [])
    {
        $note = new Note();
        $note->setElement($object);
        $note->setDate(time());
        $note->setType($type);
        $note->setTitle($title);
        $note->setUser($userId);
        $note->save();
    }
}
