=== Plugin Name ===
Contributors: arickmann
Donate link: http://www.wp-fun.co.uk/fun-with-in-context-comments/
Tags: Comments, Context, Readers
Requires at least: 2.5
Tested up to: 2.5.2
Stable tag: 0.3

This plugin lets you ask your commenters extra questions, for them to select from a drop down box, when they leave you comments.

== Description ==

The plugin lets you set the questions, and possible answers, for commenters to select from when they leave a comment.

This is intended for use in situations where you are offering support and need to know what version of a theme, plugin, and / or WordPress the person is used. Although it could be used for other things as well.

The questions can be created in two ways. Centrally, and then attached to any post, or they can be set on each post individually.

It requires you to insert a template tag into the comments.php file of your theme.


== Installation ==

Add to the plugin directory then turn it on.

Once activited you will find that there is a new tab beneath the comments menu called Global Contexts and a new advanced option on the write page called Comment Context.

You don't need to modify any files for the plugin to work, it will add all of the necessary content; however, there are two template tags which you can use if you want to change the way the content is output.

= Filter Fields =

The following template tag controls where, and how the filter fields are inserted. By default these are included before the comment page is added.

The filter title takes either a string, containing the full html for the title, or false, in which case it will use the default title.

`<?php comment_context_filter_fields( '<h3>Filter Title</h3>' ); ?>`

If you use this tag then the automatic system that inserts the default tag will turn off, but you will need to load the page twice before you see the difference.

= Context Fields =

By default the options that the commenter has are presented underneat the comment area, but you can use the following template tag to change that position:

`<?php comment_context_fields(); ?>`

I recommend placing this after the commenters details, but before the comment textarea. As with the other templte tag, using this will prevent the automatic system from including it further on down the page.

Now you are ready to use the plugin. Check out the Usage section.

== Usage ==

There are two places where you can modify how this plugin works.

1) On the bottom of each post, or page, screen there is a new area called 'Comment Contexts'; and
2) If you click on the Comments main menu you will see a new page called 'Global Contexts'.

We will start with the second of these.

Once you have entered the Global Contexts page you will notice that you can alter the way the users results are displayed. For now leave the settings as they are.

The first thing you should do is to create a test question, so scroll down to the form at the bottom of the page. The interface should be self-evident. To add a new one complete the fields:

Question, this is the actual question displayed to readers.
Caption, this is what is displayed on the comment itself once it is published on the page. Think of it as a title.
The values available, this is a list of the values the user can choose from. Enter one per line.

Now hit save. You should see an entry in the table above the form.

To edit the question click on the title.

To add this question to an actual post visit the editing screen for that post. Scroll down to the advanced area and you should an area for 'Comment contexts'. 

You will see a checkbox for each global question that you added. Checking the box and saving the post will ask users that question.

Alternatively, or as well as, you can add one or more questions that are specific to this post using the same fields below the checkboxes.

If you want to change the way the comment contexts are displayed you can do so by amending the settings at the top of the global contexts page. 

The contexts are displayed in the following format:

[Before Results Text][Context Title][Separator][Context Result][Between Contexts Text][next]

or

[No Results Text]

In either case the text that makes up each of the above is inserted into the template in place of '%content%'.

So, if the template is `<div>%content%</div>` then comments will look like:

`<div>[Before Results Text][Context Title][Separator][Context Result][Between Contexts Text][next]</div>` 

= Aggregating Results =

The plugin features two shortcodes that can be used to list the results of a particular question.

The first shortcode displays the results as an unordered list:

`[context_count title="" ]`

The title must be the title of the context. So if you have asked your commenters what version of WordPress you are using, and given it the title 'WordPress Version' then you use 'WordPress Version'.

If you want to limit the count so it only uses a person's answer once then include `count_individuals="true"`. This will use only the most recent answer from each commenter. If you want to exclude one or more commenters from the count you can use `exclude_emails` and enter a list of e-mails separated by a comma.

The second shortcode displays the results as a bar chart using the Google Charts API.

`[context_count_graph title=""]`

In addition to the comment_count options the following options are also available for the graph:

*  height - the height in pixels, use numbers only
*  width - the width in pixels, use numbers only
*  direction - use the letter h, or the letter v to determine which way up the graph is

Depending on the direction chosen a minimum height, or width will be calculated so that the graph isn't clipped when a commenter selects a choice that hasn't been chosen before.

*  color - a hex value (without the #) e.g., cccccc
*  colour - a hex value (without the #) e.g. cccccc but spelled nicely.
*  chart_title - the title to display at the top of the chart
*  bar_width - width of each bar in the chart, in pixels, use numbers only.
*  bar_spacing - the spaces betwee each bar in the chart in pixles, use numbers only.
*  background_fill - a hex value (without the #) e.g. cccccc