<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SanitizeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $event->setData($this->sanitize($data));
        });
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            $v = trim($value);
            // Remove HTML tags and control chars; Twig will still escape output by default.
            $v = strip_tags($v);
            $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
            return $v;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->sanitize($v);
            }
            return $value;
        }
        return $value;
    }
}

