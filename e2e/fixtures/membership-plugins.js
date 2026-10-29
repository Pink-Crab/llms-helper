// The membership plugins Membership_Plugin_Access reads. `slug` is the key llms-e2e-access.php uses and `folder` the
// installed plugin folder, which also tells whether a `source` plugin is already installed. `wordpressOrg` ones are
// installed by slug; `source` ones from that zip, as Paid Memberships Pro left wordpress.org for GitHub.
// WooCommerce Memberships is paid: e2e/plugins/woocommerce-memberships stands in for it, unless .wp-env.override.json
// maps the real plugin over it. `siteGate` ones can close the whole
// site to visitors. `shortcode` wraps content for members only, %s marking where it goes. `blockAttrs` are the block
// attributes that show a block to logged in users only.
const MEMBERSHIP_PLUGINS = [
	{
		slug: 'paid-memberships-pro',
		// WP-CLI installs the tag zip as paid-memberships-pro, without the version.
		folder: 'paid-memberships-pro',
		source: 'https://github.com/strangerstudios/paid-memberships-pro/archive/refs/tags/3.8.9.zip',
		name: 'Paid Memberships Pro',
		wordpressOrg: false,
		requires: [],
		shortcode: '[membership]%s[/membership]',
	},
	{ slug: 'members', folder: 'members', name: 'Members', wordpressOrg: true, requires: [], siteGate: true, shortcode: '[members_logged_in]%s[/members_logged_in]' },
	{
		slug: 'content-control',
		folder: 'content-control',
		name: 'Content Control',
		wordpressOrg: true,
		requires: [],
		shortcode: '[content_control]%s[/content_control]',
		blockAttrs: '{"contentControls":{"enabled":true,"rules":{"user":{"userStatus":"logged_in"}}}}',
	},
	{ slug: 'ultimate-member', folder: 'ultimate-member', name: 'Ultimate Member', wordpressOrg: true, requires: [], siteGate: true, shortcode: '[um_loggedin]%s[/um_loggedin]' },
	{ slug: 'restrict-content', folder: 'restrict-content', name: 'Restrict Content', wordpressOrg: true, requires: [], shortcode: '[restrict]%s[/restrict]' },
	// With no id, LifterLMS checks enrolment in the post itself, which a visitor never has.
	{ slug: 'lifterlms', folder: 'lifterlms', name: 'LifterLMS', wordpressOrg: true, requires: [], shortcode: '[lifterlms_hide_content]%s[/lifterlms_hide_content]' },
	{ slug: 'woocommerce-memberships', folder: 'woocommerce-memberships', name: 'WooCommerce Memberships', wordpressOrg: false, requires: [ 'woocommerce' ], shortcode: '[wcm_restrict]%s[/wcm_restrict]' },
];

module.exports = { MEMBERSHIP_PLUGINS };
