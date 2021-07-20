<?php

namespace Drupal\mailchimpbridge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mailchimpbridge.settings');

    $form['server'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Server:'),
      '#default_value' => $config->get('mailchimpbridge.server'),
      '#description' => $this->t('Mailchimp server.'),
    );

    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API key:'),
      '#default_value' => $config->get('mailchimpbridge.api_key'),
      '#description' => $this->t('Mailchimp api key.'),
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {

  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('mailchimpbridge.settings');
    $config->set('mailchimpbridge.server', $form_state->getValue('server'));
    $config->set('mailchimpbridge.api_key', $form_state->getValue('api_key'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

  protected function getEditableConfigNames(): array {
    return [
      'mailchimpbridge.settings',
    ];
  }

}
