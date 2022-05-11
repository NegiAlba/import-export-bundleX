<?php

namespace Galilee\ImportExportBundle\Uninstaller;

use Galilee\ImportExportBundle\Helper\BrickHelper;
use Pimcore\Model\DataObject\Objectbrick;

class BrandName extends AbstractUninstaller
{
    /**
     * @throws \Exception
     */
    public function uninstall()
    {
        $attributes = [];
        $brickKey = BrickHelper::getBrickKeyFromAttributeSet('default');
        /** @var \Pimcore\Model\DataObject\Objectbrick\Definition $brick */
        $brick = Objectbrick\Definition::getByKey($brickKey);
        /** @var \Pimcore\Model\DataObject\ClassDefinition\Layout $layout */
        $layout = $brick->getLayoutDefinitions();
        $existingFields = BrickHelper::getRecursiveFields($layout);
        foreach ($existingFields as $existingField) {
            /** @var \Pimcore\Model\DataObject\ClassDefinition\Data $field */
            $field = $existingField['field'];
            if ($field->getName() != "brand_name") {
                $attributes[] = [
                    'group' => $existingField['group'],
                    'name' => $field->getName(),
                    'title' => $field->getTitle(),
                    'fieldType' => $field->getFieldType(),
                    'options' => BrickHelper::getOptionsFromFields($field)
                ];
            }
        }
        BrickHelper::createBrick($brickKey, 'default', $attributes);
    }
}
