<?php

namespace Galilee\ImportExportBundle\EventListener;

use Galilee\ImportExportBundle\Helper\PimHelper;
use Pimcore;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Tool\Admin;

class DataObjectListener
{

    public function onPreUpdate(ElementEventInterface $e)
    {
        if ($e instanceof DataObjectEvent && Pimcore::inAdmin()) {
            $object = $e->getObject();
            if ($object instanceof Product) {
                if (count($object->getNewItemUnit()) <= 0 xor count($object->getNewItemExtCategoryId()) <= 0) {
                    throw new Pimcore\Model\Element\ValidationException("Si au moins un des champs Unité de vente Eiffage et Catégorie Eiffage est renseigné alors les 2 champs doivent l'être.");
                }
                if (count($object->getWebsites()) <= 0 xor count($object->getStoreViews()) <= 0) {
                    throw new Pimcore\Model\Element\ValidationException("Si au moins un des champs Websites et Store Views est renseigné alors les 2 champs sont obligatoires.");
                }
                $oldObject = Product::getById($object->getId(), true);
                if ($oldObject->getStatusImportSocoda() != $object->getStatusImportSocoda()) {

                    PimHelper::addStatusNote(
                        $object,
                        Admin::getCurrentUser()->getId(),
                        PimHelper::NOTE_TYPE_MANUAL,
                        $object->getStatusImportSocoda()
                    );

                }

            }
        }
    }
}
