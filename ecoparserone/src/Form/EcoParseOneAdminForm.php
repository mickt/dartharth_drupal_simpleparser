<?php

namespace Drupal\ecoparseone\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class EcoParseOneAdminForm extends FormBase {

    public function getFormId() {
        return 'ecoparseone_admin_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Run Test'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $processedCount = \Drupal::service('ecoparseone.custom_controller')->checkNewArticles();

        $this->messenger()->addMessage($this->t('Test completed. Processed articles count: @count.', ['@count' => $processedCount]));
    }
}
