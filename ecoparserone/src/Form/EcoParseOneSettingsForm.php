<?php

namespace Drupal\ecoparseone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;

class EcoParseOneSettingsForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ecoparseone_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return ['ecoparseone.settings'];
    }

    /**
     * {@inheritdoc}
     */

    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('ecoparseone.settings');


        $form['source_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Source URL'),
            '#description' => $this->t('Enter the URL of the page to parse articles from.'),
            '#default_value' => $config->get('source_url'),
            '#required' => TRUE,
            '#maxlength' => 2048,
        ];


        $form['xpath_articles'] = [
            '#type' => 'textfield',
            '#title' => $this->t('XPath for articles links'),
            '#description' => $this->t('Enter the XPath query to extract article links from the source page. Example: "//div[@class=\'list\']//a"'),
            '#default_value' => $config->get('xpath_articles'),
            '#required' => TRUE,
        ];


        $form['articles_limit'] = [
            '#type' => 'number',
            '#title' => $this->t('Number of articles to parse'),
            '#description' => $this->t('Enter the number of articles to be parsed.'),
            '#default_value' => $config->get('articles_limit'),
            '#min' => 1,
            '#max' => 50,
            '#required' => TRUE,
        ];

        $form['content_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Content type'),
            '#description' => $this->t('Select the content type to save parsed articles.'),
            '#options' => node_type_get_names(),
            '#default_value' => $config->get('content_type'),
        ];

        $form['text_format'] = [
            '#type' => 'select',
            '#title' => $this->t('Text Format'),
            '#description' => $this->t('Select the text format for the articles.'),
            '#options' => $this->getTextFormatsOptions(),
            '#default_value' => $config->get('text_format'),
        ];

        $form['xpath_article_content'] = [
            '#type' => 'textfield',
            '#title' => $this->t('XPath for article content'),
            '#description' => $this->t('Enter the XPath query to extract the article content.'),
            '#default_value' => $config->get('xpath_article_content'),
            '#required' => TRUE,
        ];

        $form['published_status'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Publish articles by default'),
            '#description' => $this->t('If checked, all new articles will be published by default.'),
            '#default_value' => $config->get('published_status'),
        ];

        $form['parsing_mode'] = [
            '#type' => 'radios',
            '#title' => $this->t('Parsing Mode'),
            '#description' => $this->t('Choose how the parsing should be triggered.'),
            '#options' => [
                'manual' => $this->t('Manual - by clicking "Start parsing" button'),
                'cron' => $this->t('Automatic - once per day via cron'),
            ],
            '#default_value' => $config->get('parsing_mode'),
        ];

        $form['actions']['start_parsing'] = [
            '#type' => 'submit',
            '#value' => $this->t('Start Parsing'),
            '#submit' => ['::startParsingSubmit'],
            '#button_type' => 'primary',
        ];

        return parent::buildForm($form, $form_state);
    }

    private function getTextFormatsOptions() {
        $formats = FilterFormat::loadMultiple();
        $options = [];
        foreach ($formats as $format) {
            $options[$format->id()] = $format->label();
        }
        return $options;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        $url = $form_state->getValue('source_url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $form_state->setErrorByName('source_url', $this->t('The URL is not valid.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $contentType = $form_state->getValue('content_type');
        $textFormat = $form_state->getValue('text_format');

        $this->config('ecoparseone.settings')
            ->set('source_url', $form_state->getValue('source_url'))
            ->set('xpath_articles', $form_state->getValue('xpath_articles'))
            ->set('content_type', $contentType)
            ->set('text_format', $textFormat)
            ->set('xpath_article_content', $form_state->getValue('xpath_article_content'))
            ->set('published_status', $form_state->getValue('published_status'))
            ->set('articles_limit', $form_state->getValue('articles_limit'))
            ->set('parsing_mode', $form_state->getValue('parsing_mode'))
            ->save();

        parent::submitForm($form, $form_state);
    }

    public function startParsingSubmit(array &$form, FormStateInterface $form_state) {
        \Drupal::messenger()->addMessage($this->t('Manual parsing has started.'));

        \Drupal::service('ecoparseone.custom_controller')->checkNewArticles();

        $form_state->setRebuild(TRUE);
    }
}
