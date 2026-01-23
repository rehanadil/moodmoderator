=== MoodModerator - AI Comment Moderation & Sentiment Analysis ===
Contributors: rehanadil
Tags: comments, moderation, ai, spam, filter
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically detect and moderate negative comments using AI sentiment analysis. Keep your WordPress community positive with intelligent comment moderation powered by OpenAI.

== Description ==

MoodModerator is a powerful WordPress plugin that uses artificial intelligence to analyze the sentiment of comments on your website. It automatically detects the tone of comments (Friendly, Toxic, Sarcastic, Questioning, Angry, Neutral, etc.) and can automatically hold negative comments for moderation.

### Key Features

* **AI-Powered Sentiment Analysis** - Uses OpenAI's GPT-4o-mini for accurate tone detection
* **Automatic Moderation** - Hold negative comments for review based on configurable strictness levels
* **Hybrid Tone Approach** - Combines predefined tones with AI-suggested new tones
* **Comprehensive Analytics** - View sentiment breakdown by post and overall statistics
* **Dashboard Widget** - 30-day sentiment summary right on your WordPress dashboard
* **Comments Table Integration** - See tone badges directly in your Comments admin table
* **Posts Table Integration** - View average sentiment for each post at a glance
* **Advanced Caching** - Minimizes API costs by caching sentiment results
* **Detailed Logging** - Track all API calls, errors, and moderation decisions
* **Configurable Strictness Levels**:
  * **Low** - Only hold Toxic and Angry comments
  * **Medium** - Hold Toxic, Angry, and Sarcastic comments (Recommended)
  * **High** - Hold all except Friendly, Questioning, and Neutral
  * **Custom** - Choose specific tones to auto-hold

### How It Works

1. When a comment is submitted, MoodModerator sends it to OpenAI for analysis
2. The AI returns a tone classification and confidence score
3. Based on your strictness settings, the plugin decides whether to hold the comment
4. Sentiment data is saved and displayed throughout the WordPress admin
5. You can review held comments and make final moderation decisions

### Requirements

* WordPress 6.0 or higher
* PHP 7.4 or higher
* OpenAI API key ([Get one here](https://platform.openai.com/api-keys))

== Third-Party Services ==

This plugin connects to OpenAI to analyze comment sentiment.

Service: OpenAI API
- Purpose: Analyze comment tone and return a sentiment classification.
- Data sent: Comment text and (optionally) the post title for context.
- When sent: On comment submission or when comments are re-analyzed.
- Service URL: https://api.openai.com/v1/chat/completions
- Terms of Service: https://openai.com/policies/terms-of-use
- Privacy Policy: https://openai.com/policies/privacy-policy

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/moodmoderator` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > MoodModerator to configure your OpenAI API key
4. Choose your preferred strictness level
5. That's it! Comments will now be automatically analyzed

== Frequently Asked Questions ==

= Do I need an OpenAI account? =

Yes, you need an OpenAI API key. You can sign up at [platform.openai.com](https://platform.openai.com/) and get your API key from the API Keys section.

= How much does it cost to use? =

The plugin itself is free, but OpenAI charges per API request. Using GPT-4o-mini (the model used by this plugin), costs are approximately $0.00015 per comment. For example, 1,000 comments would cost around $0.15.

= Will this work with existing comments? =

The plugin only analyzes new comments as they're submitted. Existing comments are not automatically analyzed, but you can manually trigger re-analysis by editing them.

= What if the API is down? =

If the OpenAI API is unavailable, the plugin logs the error and allows the comment through using WordPress's default moderation rules. Comments are never lost.

= Can I customize the tone categories? =

Yes! The plugin uses a hybrid approach. It starts with predefined tones (Friendly, Toxic, Sarcastic, Questioning, Angry, Neutral) but the AI can suggest new tones. You can approve these suggestions and they'll become available for custom strictness settings.

= Does it work with Akismet? =

Yes! MoodModerator runs after Akismet (priority 11) so spam comments are already filtered out before sentiment analysis.

= How does caching work? =

The plugin caches sentiment results for 24 hours by default (configurable). This means if a comment is edited, it will be re-analyzed. This saves API costs for unchanged comments.

= Can I export the logs? =

Yes! The Logs page (Tools > MoodModerator Logs) allows you to filter and view all plugin activity.

= Will this slow down my site? =

No. The API call happens during comment submission, and with WordPress's default comment flow, users don't see any delays. The 5-second timeout ensures the site remains responsive even if the API is slow.

== Screenshots ==

1. Settings page with API configuration and strictness levels
2. Comments table with tone badges
3. Dashboard widget showing 30-day sentiment summary

== Changelog ==

= 1.0.0 =
* Initial release
* AI-powered sentiment analysis using OpenAI GPT-4o-mini
* Configurable strictness levels (Low, Medium, High, Custom)
* Hybrid tone approach (predefined + AI-suggested)
* Comments table tone column with filtering
* Posts table average sentiment column
* Dashboard widget with 30-day summary
* Post edit screen sentiment analytics
* Comprehensive logging system
* Smart caching to minimize API costs
* Rate limiting (100 calls/hour)
* Full internationalization support

== Upgrade Notice ==

= 1.0.0 =
Initial release of MoodModerator. Requires PHP 7.4+ and WordPress 6.0+.

== Privacy Policy ==

MoodModerator sends comment text to OpenAI's API for sentiment analysis. Please review [OpenAI's privacy policy](https://openai.com/policies/privacy-policy) to understand how they handle data. Comment text is only sent for analysis purposes and is not stored by OpenAI beyond their standard retention policies.

The plugin does not collect any user data beyond what's necessary for comment analysis (comment text and optional post title for context). Site owners should disclose this third-party processing in their privacy policy.

== Support ==

For support, feature requests, or bug reports, please visit the [plugin support forum](https://wordpress.org/support/plugin/moodmoderator/) or https://rehanadil.dev.
