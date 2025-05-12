<?php

declare(strict_types=1);

namespace AlengoCustomerDiscount\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'alengoCustomerDiscount';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Customer discount settings',
                'de-DE' => 'Einstellungen für Kundenrabatte',
                Defaults::LANGUAGE_SYSTEM => 'Customer discount settings',
            ],
        ],
        'customFields' => [
            [
                'name' => 'alengoCustomerDiscount_name',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Discount name',
                        'de-DE' => 'Rabattname',
                        Defaults::LANGUAGE_SYSTEM => 'Discount name',
                    ],
                    'customFieldPosition' => 0,
                ],
            ],
            [
                'name' => 'alengoCustomerDiscount_amount',
                'type' => CustomFieldTypes::FLOAT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Discount amount',
                        'de-DE' => 'Rabattbetrag',
                        Defaults::LANGUAGE_SYSTEM => 'Discount amount',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
            [
                'name' => 'alengoCustomerDiscount_expirationDate',
                'type' => CustomFieldTypes::DATE,
                'config' => [
                    'label' => [
                        'en-GB' => 'Expiration date',
                        'de-DE' => 'Ablaufdatum',
                        Defaults::LANGUAGE_SYSTEM => 'Expiration date',
                    ],
                    'customFieldPosition' => 2,
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $existingFieldSet = $this->getExistingCustomFieldSet($context);

        if (null === $existingFieldSet) {
            $this->customFieldSetRepository->upsert([
                self::CUSTOM_FIELDSET,
            ], $context);
        } else {
            // Synchronize custom fields if the field set already exists
            $customFieldsToAdd = array_filter(self::CUSTOM_FIELDSET['customFields'], function ($customField) use ($context) {
                return !$this->customFieldExists($customField['name'], $context);
            });

            if (!empty($customFieldsToAdd)) {
                $this->customFieldSetRepository->upsert([
                    [
                        'id' => $existingFieldSet['id'],
                        'customFields' => $customFieldsToAdd,
                    ],
                ], $context);
            }
        }
    }

    public function addRelations(Context $context): void
    {
        $customFieldSetIds = $this->getCustomFieldSetIds($context);

        $relationsToAdd = array_filter(array_map(function (string $customFieldSetId) use ($context) {
            if (!$this->relationExists($customFieldSetId, 'customer', $context)) {
                return [
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => 'customer',
                ];
            }

            return null;
        }, $customFieldSetIds));

        if (!empty($relationsToAdd)) {
            $this->customFieldSetRelationRepository->upsert($relationsToAdd, $context);
        }
    }

    public function uninstall(Context $context): void
    {
        $customFieldSetIds = $this->getCustomFieldSetIds($context);

        if (!empty($customFieldSetIds)) {
            // Remove relations
            $this->customFieldSetRelationRepository->delete(
                array_map(fn ($id) => ['customFieldSetId' => $id], $customFieldSetIds),
                $context
            );

            // Remove custom field sets
            $this->customFieldSetRepository->delete(
                array_map(fn ($id) => ['id' => $id], $customFieldSetIds),
                $context
            );
        }
    }

    private function relationExists(string $customFieldSetId, string $entityName, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->search($criteria, $context)->getTotal() > 0;
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function getExistingCustomFieldSet(Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        $result = $this->customFieldSetRepository->search($criteria, $context);

        return $result->first() ? $result->first()->getVars() : null;
    }

    private function customFieldExists(string $fieldName, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $fieldName));

        return $this->customFieldRepository->search($criteria, $context)->getTotal() > 0;
    }
}
