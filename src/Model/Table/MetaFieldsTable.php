<?php

namespace App\Model\Table;

use App\Model\Table\AppTable;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

class MetaFieldsTable extends AppTable
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('UUID');
        $this->addBehavior('Timestamp');
        $this->addBehavior('CounterCache', [
            'MetaTemplateFields' => ['counter']
        ]);

        $this->belongsTo('MetaTemplates');
        $this->belongsTo('MetaTemplateFields');

        $this->setDisplayField('field');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('scope')
            ->notEmptyString('field')
            ->notEmptyString('uuid')
            ->notEmptyString('value')
            ->notEmptyString('meta_template_id')
            ->notEmptyString('meta_template_field_id')
            ->requirePresence(['scope', 'field', 'value', 'uuid', 'meta_template_id', 'meta_template_field_id'], 'create');

        $validator->add('value', 'validMetaField', [
            'rule' => 'isValidMetaField',
            'message' => __('The provided value doesn\'t satisfy the validation defined by the meta-fields\'s meta-template'),
            'provider' => 'table',
        ]);

        return $validator;
    }

    public function isValidMetaField($value, array $context)
    {
        $metaFieldsTable = $context['providers']['table'];
        $entityData = $context['data'];
        $metaTemplateField = $metaFieldsTable->MetaTemplateFields->get($entityData['meta_template_field_id']);
        $typeValid = $this->isValidType($value, $metaTemplateField['type']);
        if ($typeValid !== true) {
            return $typeValid;
        }
        if (!empty($metaTemplateField['regex'])) {
            return $this->isValidRegex($value, $metaTemplateField);
        }
        return true;
    }

    public function isValidMetaFieldOLD($entity, $options)
    {
        debug($entity['value']);
        $metaFieldsTable = $options['repository'];
        $metaTemplateField = $metaFieldsTable->MetaTemplateFields->get($entity['meta_template_field_id']);
        $typeValid = $this->isValidType($entity['value'], $metaTemplateField['type']);
        if ($typeValid !== true) {
            return $typeValid;
        }
        $metaTemplateField['regex'] = '/123/';
        if (!empty($metaTemplateField['regex'])) {
            return $this->isValidRegex($entity['value'], $metaTemplateField);
        }
        return true;
    }

    public function isValidType($value, string $type)
    {
        if (empty($value)) {
            return __('Metafield value cannot be empty.');
        }
        return true;
    }

    public function isValidRegex($value, $metaTemplateField)
    {
        if (!preg_match($metaTemplateField['regex'], $value)) {
            return __('Metafield value `{0}` for `{1}` doesn\'t pass regex validation', $value, $metaTemplateField->field);
        }
        return true;
    }
}
