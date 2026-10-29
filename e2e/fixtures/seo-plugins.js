// The SEO plugins Seo_Plugin_Noindex and Seo_Plugin_Description read, by wordpress.org slug. `siteDescription`
// ones also give the site's description to llms.txt.
const SEO_PLUGINS = [
	{ slug: 'wordpress-seo', name: 'Yoast SEO', siteDescription: true },
	{ slug: 'seo-by-rank-math', name: 'Rank Math', siteDescription: true },
	{ slug: 'wp-seopress', name: 'SEOPress' },
	{ slug: 'all-in-one-seo-pack', name: 'AIOSEO' },
	{ slug: 'slim-seo', name: 'Slim SEO' },
];

module.exports = { SEO_PLUGINS };
