<?php

namespace App\Form;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class YamlType extends AbstractType
{
    private array $options;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->options = $options;

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $e) {
            $this->validateData($e->getData(), $e->getForm());
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'schema' => null,
        ]);
    }

    public function getParent()
    {
        return TextareaType::class;
    }

    private function validateData($input, Form $form)
    {
        try {
            $data = Yaml::parse($input, YAML::PARSE_OBJECT_FOR_MAP);
            if (!empty($this->options['schema'])) {
                // @fixme
                // $schemaContent = file_get_contents($this->options['schema']);
                // $schemaData = (0 === strpos($schemaContent,
                //         '{')) ? json_decode($schemaContent) : Yaml::parse($schemaContent,
                //     YAML::PARSE_OBJECT_FOR_MAP);

                // $validator = new Validator();
                // $validator->resolver()->registerFile('schema', $this->options['schema']);
                // $result = $validator->validate($data, 'schema');

                // if (!$result->isValid()) {
                //     foreach ($result->getErrors() as $error) {
                //         $form->addError(new FormError(json_encode([$error->keyword(), $error->keywordArgs()])));
                //     }
                // }
            }
        } catch (\Exception $exception) {
            $form->addError(new FormError($exception->getMessage()));
        }
    }
}
