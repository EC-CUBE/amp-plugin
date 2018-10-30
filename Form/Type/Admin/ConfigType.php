<?php

namespace Plugin\Amp4\Form\Type\Admin;

use function foo\func;
use Plugin\Amp4\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ConfigType
 * @package Plugin\Amp4\Form\Type\Admin
 */
class ConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('amp_twig_api_url', TextType::class, [
                'label' => trans('amp4.admin.config.amp_twig_api_url.name'),
                'required' => false,
                'constraints' => [
                    new Assert\Length(['max' => 1024]),
                    new Assert\Url(),
                ],
            ])
            ->add('canonical', ChoiceType::class, [
                //'label' => trans('amp4.admin.config.canonical.name'),
                'required' => true,
                'choices'  => [
                    'AMP Cache' => false,
                    'Canonical AMP' => true,
                ],
                'expanded' => true,
            ])
            ->add('optimize', CheckboxType::class, [
                'label' => trans('amp4.admin.config.optimize.option.name'),
                'required' => false,
            ])
            ->add('amp_header_css', TextareaType::class, [
                'label' => trans('amp4.admin.config.amp_header_css.name'),
                'required' => false,
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();
                $canonical = $form['canonical']->getData();
                $optimize = $form['optimize']->getData();

                if (!$canonical && $optimize) {
                    $message = trans('amp4.admin.config.not_canonical_optimize_error');
                    $form['optimize']->addError(new FormError($message));
                } else {
                    if ($optimize) {
                        $apiUrl = $form['amp_twig_api_url']->getData();
                        if (!$apiUrl) {
                            $message = trans('amp4.admin.config.not_api_url_optimize_error');
                            $form['amp_twig_api_url']->addError(new FormError($message));
                        }
                    }
                }
            });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}