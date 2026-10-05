<?php
/**
 * Starter templates for new forms.
 *
 * @package Glixform
 */

namespace Glixform\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Each template is a regular form definition run through FormRepository::sanitize().
 * Add-ons can add templates with the "glixform_templates" filter.
 */
class Templates {

	/**
	 * Form storage (for sanitizing).
	 *
	 * @var FormRepository
	 */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms Forms.
	 */
	public function __construct( FormRepository $forms ) {
		$this->forms = $forms;
	}

	/**
	 * All templates, sanitized.
	 *
	 * @return array[] slug, name, description, icon, data.
	 */
	public function all() {
		/**
		 * Filters the form templates.
		 *
		 * @param array $templates Templates: slug => [ name, description, icon, fields, settings ].
		 */
		$templates = apply_filters( 'glixform_templates', $this->definitions() );

		$out = array();
		foreach ( $templates as $slug => $template ) {
			$fields = (array) ( $template['fields'] ?? array() );
			$id     = 1;
			foreach ( $fields as &$field ) {
				$field['id'] = $field['id'] ?? $id;
				$id          = max( $id, (int) $field['id'] ) + 1;
			}
			unset( $field );

			$out[] = array(
				'slug'        => sanitize_key( $slug ),
				'name'        => (string) ( $template['name'] ?? $slug ),
				'description' => (string) ( $template['description'] ?? '' ),
				'icon'        => (string) ( $template['icon'] ?? 'dashicons-feedback' ),
				'data'        => $this->forms->sanitize(
					array(
						'fields'        => $fields,
						'settings'      => (array) ( $template['settings'] ?? array() ),
						'next_field_id' => $id,
					)
				),
			);
		}
		return $out;
	}

	/**
	 * One template by slug.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public function get( $slug ) {
		foreach ( $this->all() as $template ) {
			if ( $template['slug'] === $slug ) {
				return $template;
			}
		}
		return null;
	}

	/**
	 * Built-in templates.
	 *
	 * @return array
	 */
	private function definitions() {
		$name    = array(
			'type'     => 'name',
			'label'    => __( 'Name', 'glixform' ),
			'required' => true,
		);
		$email   = array(
			'type'     => 'email',
			'label'    => __( 'Email', 'glixform' ),
			'required' => true,
		);
		$phone   = array(
			'type'  => 'phone',
			'label' => __( 'Phone', 'glixform' ),
		);
		$message = array(
			'type'     => 'textarea',
			'label'    => __( 'Message', 'glixform' ),
			'required' => true,
		);

		return array(
			'blank'       => array(
				'name'        => __( 'Blank form', 'glixform' ),
				'description' => __( 'Start from scratch and add the fields you need.', 'glixform' ),
				'icon'        => 'dashicons-plus-alt2',
				'fields'      => array(),
			),
			'contact'     => array(
				'name'        => __( 'Simple contact form', 'glixform' ),
				'description' => __( 'Name, email and message: the classic.', 'glixform' ),
				'icon'        => 'dashicons-email-alt',
				'fields'      => array( $name, $email, $message ),
			),
			'support'     => array(
				'name'        => __( 'Support request', 'glixform' ),
				'description' => __( 'Collect the details your team needs to help, with an optional screenshot.', 'glixform' ),
				'icon'        => 'dashicons-sos',
				'fields'      => array(
					$name,
					$email,
					array(
						'id'       => 3,
						'type'     => 'select',
						'label'    => __( 'Topic', 'glixform' ),
						'required' => true,
						'choices'  => array( array( 'label' => __( 'Billing', 'glixform' ) ), array( 'label' => __( 'Technical issue', 'glixform' ) ), array( 'label' => __( 'Account', 'glixform' ) ), array( 'label' => __( 'Other', 'glixform' ) ) ),
					),
					array(
						'id'       => 4,
						'type'     => 'radio',
						'label'    => __( 'Priority', 'glixform' ),
						'required' => true,
						'choices'  => array(
							array(
								'label'   => __( 'Low', 'glixform' ),
								'default' => false,
							),
							array(
								'label'   => __( 'Normal', 'glixform' ),
								'default' => true,
							),
							array(
								'label'   => __( 'Urgent', 'glixform' ),
								'default' => false,
							),
						),
					),
					array(
						'id'       => 5,
						'type'     => 'textarea',
						'label'    => __( 'Describe the problem', 'glixform' ),
						'required' => true,
					),
					array(
						'id'                 => 6,
						'type'               => 'file',
						'label'              => __( 'Screenshot (optional)', 'glixform' ),
						'allowed_extensions' => 'jpg, jpeg, png, gif, webp, pdf',
					),
				),
			),
			'quote'       => array(
				'name'        => __( 'Request a quote', 'glixform' ),
				'description' => __( 'Two steps: project details, then contact information.', 'glixform' ),
				'icon'        => 'dashicons-money-alt',
				'fields'      => array(
					array(
						'type'     => 'checkbox',
						'label'    => __( 'Services needed', 'glixform' ),
						'required' => true,
						'choices'  => array( array( 'label' => __( 'Design', 'glixform' ) ), array( 'label' => __( 'Development', 'glixform' ) ), array( 'label' => __( 'Marketing', 'glixform' ) ), array( 'label' => __( 'Other', 'glixform' ) ) ),
					),
					array(
						'type'     => 'select',
						'label'    => __( 'Budget', 'glixform' ),
						'required' => true,
						'choices'  => array( array( 'label' => __( 'Under $1,000', 'glixform' ) ), array( 'label' => '$1,000 – $5,000' ), array( 'label' => '$5,000 – $20,000' ), array( 'label' => __( 'Over $20,000', 'glixform' ) ) ),
					),
					array(
						'type'   => 'datetime',
						'label'  => __( 'Ideal start date', 'glixform' ),
						'format' => 'date',
					),
					array(
						'type'  => 'textarea',
						'label' => __( 'Project details', 'glixform' ),
					),
					array(
						'type'  => 'pagebreak',
						'label' => __( 'Your details', 'glixform' ),
					),
					$name,
					$email,
					$phone,
					array(
						'type'  => 'text',
						'label' => __( 'Company', 'glixform' ),
					),
				),
			),
			'feedback'    => array(
				'name'        => __( 'Customer feedback', 'glixform' ),
				'description' => __( 'A star rating plus follow-up questions that appear for low scores.', 'glixform' ),
				'icon'        => 'dashicons-star-half',
				'fields'      => array(
					array(
						'id'       => 1,
						'type'     => 'rating',
						'label'    => __( 'How would you rate your experience?', 'glixform' ),
						'required' => true,
					),
					array(
						'id'          => 2,
						'type'        => 'textarea',
						'label'       => __( 'What could we do better?', 'glixform' ),
						'conditional' => array(
							'enabled' => true,
							'action'  => 'show',
							'logic'   => 'all',
							'rules'   => array(
								array(
									'field'    => 1,
									'operator' => 'less_than',
									'value'    => '4',
								),
							),
						),
					),
					array(
						'id'      => 3,
						'type'    => 'radio',
						'label'   => __( 'Would you recommend us to a friend?', 'glixform' ),
						'choices' => array( array( 'label' => __( 'Yes', 'glixform' ) ), array( 'label' => __( 'Maybe', 'glixform' ) ), array( 'label' => __( 'No', 'glixform' ) ) ),
					),
					array(
						'id'    => 4,
						'type'  => 'email',
						'label' => __( 'Email (if you would like a reply)', 'glixform' ),
					),
				),
			),
			'newsletter'  => array(
				'name'        => __( 'Newsletter signup', 'glixform' ),
				'description' => __( 'Short signup with consent, ready for your mailing list.', 'glixform' ),
				'icon'        => 'dashicons-megaphone',
				'fields'      => array(
					array(
						'type'   => 'name',
						'label'  => __( 'First name', 'glixform' ),
						'format' => 'simple',
					),
					$email,
					array(
						'type'         => 'gdpr',
						'label'        => __( 'Consent', 'glixform' ),
						'consent_text' => __( 'Yes, send me news and offers. I can unsubscribe at any time.', 'glixform' ),
					),
				),
				'settings'    => array(
					'submit_text'   => __( 'Subscribe', 'glixform' ),
					'confirmations' => array(
						array(
							'id'      => 1,
							'message' => __( 'You are on the list. Check your inbox to confirm your subscription.', 'glixform' ),
						),
					),
				),
			),
			'event'       => array(
				'name'        => __( 'Event registration', 'glixform' ),
				'description' => __( 'Attendee details, ticket type and dietary needs.', 'glixform' ),
				'icon'        => 'dashicons-tickets-alt',
				'fields'      => array(
					$name,
					$email,
					$phone,
					array(
						'type'     => 'radio',
						'label'    => __( 'Ticket type', 'glixform' ),
						'required' => true,
						'choices'  => array( array( 'label' => __( 'General admission', 'glixform' ) ), array( 'label' => __( 'VIP', 'glixform' ) ), array( 'label' => __( 'Student', 'glixform' ) ) ),
					),
					array(
						'type'  => 'number',
						'label' => __( 'Number of guests', 'glixform' ),
						'min'   => '0',
						'max'   => '10',
					),
					array(
						'type'    => 'checkbox',
						'label'   => __( 'Dietary requirements', 'glixform' ),
						'choices' => array( array( 'label' => __( 'Vegetarian', 'glixform' ) ), array( 'label' => __( 'Vegan', 'glixform' ) ), array( 'label' => __( 'Gluten-free', 'glixform' ) ) ),
					),
				),
				'settings'    => array( 'submit_text' => __( 'Register', 'glixform' ) ),
			),
			'appointment' => array(
				'name'        => __( 'Appointment request', 'glixform' ),
				'description' => __( 'Let visitors pick a preferred date and time.', 'glixform' ),
				'icon'        => 'dashicons-calendar-alt',
				'fields'      => array(
					$name,
					$email,
					$phone,
					array(
						'type'     => 'datetime',
						'label'    => __( 'Preferred date', 'glixform' ),
						'format'   => 'date',
						'required' => true,
					),
					array(
						'type'   => 'datetime',
						'label'  => __( 'Preferred time', 'glixform' ),
						'format' => 'time',
						'min'    => '09:00',
						'max'    => '17:00',
					),
					array(
						'type'  => 'textarea',
						'label' => __( 'Anything we should know?', 'glixform' ),
					),
				),
				'settings'    => array( 'submit_text' => __( 'Request appointment', 'glixform' ) ),
			),
			'job'         => array(
				'name'        => __( 'Job application', 'glixform' ),
				'description' => __( 'Applicant details, CV upload and a cover letter.', 'glixform' ),
				'icon'        => 'dashicons-portfolio',
				'fields'      => array(
					$name,
					$email,
					$phone,
					array(
						'type'  => 'url',
						'label' => __( 'LinkedIn or portfolio', 'glixform' ),
					),
					array(
						'type'               => 'file',
						'label'              => __( 'CV / Résumé', 'glixform' ),
						'required'           => true,
						'allowed_extensions' => 'pdf, doc, docx, odt',
						'max_size'           => 10,
					),
					array(
						'type'  => 'textarea',
						'label' => __( 'Cover letter', 'glixform' ),
					),
					array(
						'type'         => 'gdpr',
						'consent_text' => __( 'I agree that my application data is stored for the recruitment process.', 'glixform' ),
					),
				),
				'settings'    => array( 'submit_text' => __( 'Apply', 'glixform' ) ),
			),
		);
	}
}
