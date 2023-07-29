<?php

echo "<div class=\"container\">\n    <div class=\"apps-and-integrations\">\n\n        <h1>\n            <a href=\"https://marketplace.whmcs.com/?utm_source=inproduct&utm_medium=poweredby\" target=\"_blank\" class=\"hidden-xs hidden-sm\">\n                <img src=\"";
echo $assetHelper->getImgPath();
echo "/powered-by-marketplace.png\" class=\"powered-by\" alt=\"Powered by WHMCS Marketplace\">\n            </a>\n            ";
echo AdminLang::trans("apps.title");
echo "        </h1>\n\n        ";
if (!empty($connectionError)) {
    echo "            <div class=\"app-wrapper error\">\n                <h3>Unable to connect to Apps and Integrations</h3>\n                <p>Your WHMCS installation is unable to connect to the Apps and Integrations data feed at this time.</p>\n                <p>Please check and ensure your server is able to communicate with <em>https://appsfeed.whmcs.com/</em> and then try again.</p>\n            </div>\n        ";
} else {
    if (!empty($renderError)) {
        echo "            <div class=\"app-wrapper error\">\n                <h3>Oops! There's a problem.</h3>\n                <p>Apps and Integrations failed to initialise. The following error occurred:</p>\n                <div class=\"alert alert-danger\" style=\"margin:20px;\">\n                    ";
        echo $renderError;
        echo "                </div>\n            </div>\n        ";
    } else {
        echo "            <div class=\"input-group search\">\n                <span class=\"input-group-btn\">\n                    <button class=\"btn btn-default\" type=\"button\"><i class=\"far fa-search\"></i></button>\n                </span>\n                <input type=\"text\" id=\"inputAppSearch\" class=\"form-control\" placeholder=\"";
        echo AdminLang::trans("apps.searchPlaceholder");
        echo "\">\n            </div>\n\n            <ul class=\"nav nav-pills aai-primary-nav\" role=\"tablist\">\n                <li role=\"presentation\" class=\"active\"><a href=\"#featured\" aria-controls=\"featured\" role=\"tab\" data-toggle=\"tab\">";
        echo AdminLang::trans("apps.nav.featured");
        echo "</a></li>\n                <li role=\"presentation\"><a href=\"#browse\" aria-controls=\"browse\" role=\"tab\" data-toggle=\"tab\" id=\"tabBrowse\">";
        echo AdminLang::trans("apps.nav.browse");
        echo "</a></li>\n                <li role=\"presentation\"><a href=\"#active\" aria-controls=\"active\" role=\"tab\" data-toggle=\"tab\" id=\"tabActive\">";
        echo AdminLang::trans("apps.nav.active");
        echo "</a></li>\n                <li role=\"presentation\"><a href=\"#search\" aria-controls=\"search\" role=\"tab\" data-toggle=\"tab\" id=\"tabSearch\">";
        echo AdminLang::trans("apps.nav.search");
        echo "</a></li>\n            </ul>\n\n            <div class=\"tab-content\">\n                <div role=\"tabpanel\" class=\"tab-pane fade in active\" id=\"featured\">\n\n                    <div class=\"app-wrapper\">\n                        <div class=\"owl-carousel owl-theme apps-hero-banners\">\n                            ";
        foreach ($heros as $hero) {
            echo "                                ";
            if ($hero->hasRemoteUrl()) {
                echo "                                    <a href=\"";
                echo urlencode($hero->getRemoteUrl());
                echo "\" target=\"_blank\" class=\"app-external-url\">\n                                ";
            } else {
                if ($hero->hasTargetAppKey()) {
                    echo "                                    <a href=\"";
                    echo routePath("admin-apps-info", $hero->getTargetAppKey());
                    echo "\" class=\"app-inner open-modal\" data-modal-class=\"app-info-modal\" data-modal-size=\"modal-lg\">\n                                ";
                }
            }
            echo "                                    <img class=\"owl-lazy\" data-src=\"";
            echo escape($hero->getImageUrl());
            echo "\">\n                                </a>\n                            ";
        }
        echo "                        </div>\n                    </div>\n\n                    <div id=\"featuredContentPane\">\n\n                        <div class=\"app-wrapper clearfix\">\n                            <div class=\"loader\">\n                                ";
        echo AdminLang::trans("global.loading");
        echo "                            </div>\n                        </div>\n\n                    </div>\n\n                </div>\n                <div role=\"tabpanel\" class=\"tab-pane fade\" id=\"browse\">\n\n                    <div id=\"browseContentPane\">\n                        <div class=\"app-wrapper\">\n                            <div class=\"loader\">\n                                ";
        echo AdminLang::trans("global.loading");
        echo "                            </div>\n                        </div>\n                    </div>\n\n                </div>\n                <div role=\"tabpanel\" class=\"tab-pane fade\" id=\"active\">\n\n                    <div class=\"app-category-title\">\n                        <h2>";
        echo AdminLang::trans("apps.nav.active");
        echo " <span>";
        echo AdminLang::trans("apps.apps");
        echo "</span></h2>\n                        <p class=\"lead\">";
        echo AdminLang::trans("apps.activeDescription");
        echo "</p>\n                    </div>\n\n                    <div class=\"app-wrapper clearfix\">\n                        <div id=\"activeContentPane\">\n                            <div class=\"loader\">\n                                ";
        echo AdminLang::trans("global.loading");
        echo "                            </div>\n                        </div>\n                    </div>\n\n                </div>\n                <div role=\"tabpanel\" class=\"tab-pane fade\" id=\"search\">\n\n                    <div id=\"searchContentPane\">\n                        ";
        $this->insert("apps/search");
        echo "                    </div>\n\n                </div>\n            </div>\n\n            <a href=\"https://marketplace.whmcs.com/?utm_source=inproduct&utm_medium=poweredby\" target=\"_blank\" class=\"visible-xs visible-sm\">\n                <img src=\"";
        echo $assetHelper->getImgPath();
        echo "/powered-by-marketplace.png\" class=\"powered-by\" alt=\"Powered by WHMCS Marketplace\">\n            </a>\n        ";
    }
}
echo "\n    </div>\n</div>\n\n<script>\nvar originalDisplayTitle = document.title;\nvar postLoadClick = false;\n\n\$(document).ready(function() {\n    \$('.contentarea').addClass('grey-bg').find('h1').first().hide();\n\n    \$(\".apps-hero-banners\").owlCarousel({\n        items: 1,\n        lazyLoad: true,\n        loop: true,\n        autoplay: true,\n        autoplayTimeout: 10000,\n        autoplayHoverPause: true\n    });\n\n    WHMCS.http.jqClient.post('";
echo routePath("admin-apps-featured");
echo "', '', function(data) {\n        \$('#featuredContentPane').html(data.content);\n    }, 'json');\n\n    \$(document).on('click', '.btn-view-all', function(e) {\n        e.preventDefault();\n        \$('#tabBrowse').data('category-slug', \$(this).data('category-slug'))\n            .data('category-display-name', \$(this).data('category-display-name'))\n            .click();\n    });\n\n    \$('.aai-primary-nav a[data-toggle=\"tab\"]').on('shown.bs.tab', function (e) {\n        var tabName = \$(e.target).attr('aria-controls');\n        if (tabName == 'featured') {\n            document.title = originalDisplayTitle;\n            window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-index");
echo "\");\n        } else if (tabName == 'browse') {\n            var categorySlug = \$('#tabBrowse').data('category-slug');\n            var categoryDisplayName = \$('#tabBrowse').data('category-display-name');\n            if (!categorySlug) {\n                categorySlug = \$('.featured-cat').first().data('slug');\n            }\n            if (!\$('#browse').hasClass('loaded') || \$('#browse').data('loaded-category-slug') != categorySlug) {\n                browseCategory(categorySlug);\n            }\n            if (postLoadClick) {\n                postLoadClick = false;\n            } else {\n                if (!categoryDisplayName) {\n                    categoryDisplayName = 'Browse';\n                }\n                document.title = categoryDisplayName + ' Apps - ' + originalDisplayTitle;\n                window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-browse");
echo "\");\n            }\n        } else if (tabName == 'search') {\n            \$('#inputAppSearch').focus();\n            if (!\$('#search').hasClass('loaded')) {\n                WHMCS.http.jqClient.post('";
echo routePath("admin-apps-search");
echo "', '', function(data) {\n                    \$('#searchContentPane .search-apps-load-target').html(data.content);\n                    \$('.search-apps-regular .app.featured').detach().appendTo('.search-apps-featured');\n                    \$('#inputAppSearch').keyup();\n                }, 'json');\n                \$('#search').addClass('loaded');\n            }\n            document.title = 'Search - ' + originalDisplayTitle;\n            window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-search");
echo "\");\n        } else if (tabName == 'active') {\n            WHMCS.http.jqClient.post('";
echo routePath("admin-apps-active");
echo "', '', function(data) {\n                \$('#activeContentPane').html(data.content);\n            }, 'json');\n            document.title = 'Active Apps - ' + originalDisplayTitle;\n            window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-active");
echo "\");\n        }\n    });\n\n    \$(document).on('click', '.categories-nav a', function(e) {\n        e.preventDefault();\n        \$('.categories-nav a').removeClass('active');\n        \$(this).addClass('active').append('<i class=\"fa fa-spinner fa-spin\"></i>');\n        document.title = \$(this).data('name') + ' Apps - ' + originalDisplayTitle;\n        window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-category", "");
echo "\" + \$(this).data('slug'));\n        browseCategory(\$(this).data('slug'));\n    });\n\n    \$(document).on('change', '#inputCategoryDropdown', function(e) {\n        e.preventDefault();\n        document.title = \$(this).find(':selected').data('name') + ' Apps - ' + originalDisplayTitle;\n        window.history.pushState({\"pageTitle\": document.title}, \"\", \"";
echo routePath("admin-apps-category", "");
echo "\" + \$(this).val());\n        browseCategory(\$(this).val());\n    });\n\n    \$(document).on('submit', '.app-info-modal form', function(e) {\n        \$(this).find('button[type=\"submit\"]').prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin fa-fw\"></i> ' + \$(this).find('button[type=\"submit\"]').html());\n    });\n\n    jQuery(document).on('click', '.view-btn-container', function() {\n        var btnContainer = jQuery(this);\n        var selectedBtn = btnContainer.find('.selected');\n        var appWrapper = btnContainer.closest('.app-wrapper.category-view');\n\n        if (selectedBtn.hasClass('list-view-btn')) {\n            localStorage.setItem('apps-integrations-list-style', 'grid');\n            appWrapper.removeClass('list-view');\n        } else {\n            appWrapper.addClass('list-view');\n            localStorage.setItem('apps-integrations-list-style', 'list');\n        }\n\n        selectedBtn.removeClass('selected');\n        selectedBtn.siblings().addClass('selected');\n    });\n\n    \$('#inputAppSearch').keyup(function() {\n        var searchTerm = \$(this).val().toUpperCase();\n\n        \$('#tabSearch').click();\n\n        if (searchTerm.length < 3) {\n            \$('.min-search-term').show();\n            \$('.no-results-found').hide();\n            \$('#searchResultsCount').html('0');\n            \$('.search-wrapper').hide();\n            return;\n        }\n\n        \$('.min-search-term').hide();\n        \$('.search-apps-featured').parent('.app-wrapper').show();\n        \$('.search-apps-regular').parent('.app-wrapper').show();\n\n        var searchResultCount = 0;\n        \$('.search-apps-featured .app, .search-apps-regular .app').each(function(index) {\n            if (\$(this).text().toUpperCase().indexOf(searchTerm) > -1) {\n                \$(this).show();\n                searchResultCount++;\n            } else {\n                \$(this).hide();\n            }\n        });\n\n        \$('.search-wrapper').removeClass('hidden').show();\n\n        if (\$('.search-apps-featured .app:visible').length <= 0) {\n            \$('.search-apps-featured').parent('.app-wrapper').hide();\n        }\n        if (\$('.search-apps-regular .app:visible').length <= 0) {\n            \$('.search-apps-regular').parent('.app-wrapper').hide();\n        }\n\n        \$('#searchResultsCount').html(searchResultCount);\n        if (searchResultCount == 0) {\n            \$('.no-results-found').removeClass('hidden').show();\n        } else {\n            \$('.no-results-found').hide();\n        }\n    });\n\n    \$('#inputAppSearch').focus(function() {\n        \$('.input-group.search').addClass('active');\n    });\n    \$('#inputAppSearch').blur(function() {\n        \$('.input-group.search').removeClass('active');\n    });\n\n    \$(document).on('click', '.app-external-url', function(e) {\n        WHMCS.http.jqClient.jsonPost({\n            url: 'https://api1.whmcs.com/apps/track/external',\n            data: 'url=' + encodeURIComponent(\$(this).attr('href')),\n        });\n    });\n\n    ";
if ($postLoadAction) {
    echo "        postLoadClick = true;\n        ";
    if ($postLoadAction == "browse") {
        echo "            ";
        if (isset($postLoadParams["category"]) && $postLoadParams["category"]) {
            echo "                \$('#tabBrowse').data('category-slug', '";
            echo $postLoadParams["category"];
            echo "').click();\n            ";
        } else {
            echo "                \$('#tabBrowse').click();\n            ";
        }
        echo "        ";
    } else {
        if ($postLoadAction == "active") {
            echo "            \$('#tabActive').click();\n        ";
        } else {
            if ($postLoadAction == "search") {
                echo "            \$('#tabSearch').click();\n        ";
            }
        }
    }
    echo "    ";
}
echo "});\n\nfunction browseCategory(slug) {\n    WHMCS.http.jqClient.jsonPost({\n        url: '";
echo routePath("admin-apps-browse");
echo "/' + slug,\n        data: '',\n        success: function(data) {\n            var contentPane = jQuery('#browseContentPane');\n            document.title = data.displayname + ' Apps - ' + originalDisplayTitle;\n            contentPane.html(data.content);\n            \$('.app-wrapper.category-view').each(function() {\n                if (\$(this).find('.apps').children('.app').size() == 0) {\n                    \$(this).hide();\n                }\n            });\n            \$('#browse').addClass('loaded').data('loaded-category-slug', slug);\n            \$('#tabBrowse').data('category-slug', slug)\n                .data('category-display-name', data.displayname);\n            if (localStorage.getItem('apps-integrations-list-style') != 'list') {\n                jQuery('.view-btn-container', contentPane).click();\n            }\n        }\n    });\n}\n</script>\n\n<div class=\"clearfix\"></div>\n";
