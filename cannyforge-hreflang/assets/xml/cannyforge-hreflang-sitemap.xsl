<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xhtml="http://www.w3.org/1999/xhtml"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9">

    <xsl:output method="html" encoding="UTF-8" indent="yes" />

    <!-- Each sitemap url follows an XML comment: cannyforge-hreflang-group, colon, then slug (see PHP emitter). -->
    <xsl:key name="cf-sitemap-urls-by-group" match="sitemap:url"
        use="substring-after(normalize-space(string(preceding-sibling::comment()[1])), 'cannyforge-hreflang-group:')" />

    <xsl:template match="/">
        <html>
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>CannyForge Hreflang Sitemap</title>
            <link rel="stylesheet" type="text/css" href="__CANNYFORGE_HREFLANG_SITEMAP_CSS__" />
        </head>
        <body>
            <div class="cannyforge-hreflang-sitemap">
                <div class="cannyforge-hreflang-sitemap-header">
                    <div class="cannyforge-hreflang-sitemap-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="200" height="50">
                          <defs>
                            <linearGradient id="cfhFlame" x1="0%" y1="100%" x2="0%" y2="0%">
                              <stop offset="0%" stop-color="#E84A1C"/>
                              <stop offset="60%" stop-color="#F97316"/>
                              <stop offset="100%" stop-color="#FBBF24"/>
                            </linearGradient>
                            <linearGradient id="cfhAnvil" x1="0%" y1="0%" x2="0%" y2="100%">
                              <stop offset="0%" stop-color="#334155"/>
                              <stop offset="100%" stop-color="#0F172A"/>
                            </linearGradient>
                          </defs>
                          <rect x="8" y="42" width="46" height="16" rx="3" fill="url(#cfhAnvil)"/>
                          <path d="M12 42 L52 42 L52 32 Q52 28 48 28 L28 28 Q22 28 18 32 Z" fill="url(#cfhAnvil)"/>
                          <path d="M8 35 Q4 35 4 38 L4 42 L12 42 L12 32 Z" fill="#1E293B"/>
                          <rect x="14" y="58" width="12" height="6" rx="2" fill="#0F172A"/>
                          <rect x="36" y="58" width="12" height="6" rx="2" fill="#0F172A"/>
                          <path d="M31 10 C31 10 26 16 27 22 C25 19 23 17 24 13 C20 18 20 24 23 28 C21 27 19 25 19 22 C17 27 20 35 28 36 C35 37 39 33 39 28 C42 30 41 34 39 36 C44 33 45 26 42 22 C43 26 41 28 40 28 C42 23 40 17 37 14 C38 18 36 21 35 22 C36 17 34 12 31 10Z" fill="url(#cfhFlame)"/>
                          <circle cx="44" cy="22" r="1.5" fill="#FBBF24" opacity="0.9"/>
                          <circle cx="47" cy="18" r="1" fill="#FCD34D" opacity="0.7"/>
                          <circle cx="15" cy="20" r="1.5" fill="#FCA5A5" opacity="0.8"/>
                          <text x="68" y="50" font-family="'Segoe UI', Arial, sans-serif" font-size="30" font-weight="800" letter-spacing="-0.5" fill="#0F172A">Canny</text>
                          <text x="156" y="50" font-family="'Segoe UI', Arial, sans-serif" font-size="30" font-weight="800" letter-spacing="-0.5" fill="#E84A1C">Forge</text>
                          <text x="68" y="63" font-family="'Segoe UI', Arial, sans-serif" font-size="9" font-weight="400" letter-spacing="2" fill="#64748B">WORDPRESS PLUGINS</text>
                        </svg>
                    </div>
                    <div class="cannyforge-hreflang-sitemap-header-content">
                        <h1>Hreflang</h1>
                        <p>Multilingual page relationships for SEO optimization</p>
                    </div>
                </div>

                <div class="cannyforge-hreflang-sitemap-content">
                <xsl:variable name="cluster-heads" select="//sitemap:url[count(.|key('cf-sitemap-urls-by-group', substring-after(normalize-space(string(preceding-sibling::comment()[1])), 'cannyforge-hreflang-group:'))[1]) = 1]" />

                <div class="cannyforge-hreflang-sitemap-stats">
                    <div class="cannyforge-hreflang-sitemap-stat">
                        <span class="cannyforge-hreflang-sitemap-stat-value"><xsl:value-of select="count($cluster-heads)" /></span>
                        <span class="cannyforge-hreflang-sitemap-stat-label">Groups</span>
                    </div>
                    <div class="cannyforge-hreflang-sitemap-stat">
                        <span class="cannyforge-hreflang-sitemap-stat-value"><xsl:value-of select="count(//sitemap:url)" /></span>
                        <span class="cannyforge-hreflang-sitemap-stat-label">URLs</span>
                    </div>
                    <div class="cannyforge-hreflang-sitemap-stat">
                        <span class="cannyforge-hreflang-sitemap-stat-value"><xsl:value-of select="count(//xhtml:link[@hreflang != 'x-default'])" /></span>
                        <span class="cannyforge-hreflang-sitemap-stat-label">Locale alternates</span>
                    </div>
                    <div class="cannyforge-hreflang-sitemap-stat">
                        <span class="cannyforge-hreflang-sitemap-stat-value"><xsl:value-of select="count($cluster-heads[xhtml:link[@hreflang = 'x-default']])" /></span>
                        <span class="cannyforge-hreflang-sitemap-stat-label">Groups with x-default</span>
                    </div>
                </div>

                <xsl:choose>
                    <xsl:when test="count(//sitemap:url) = 0">
                        <div class="cannyforge-hreflang-sitemap-empty">
                            <h2>No hreflang groups configured</h2>
                            <p>Add pages to translation groups in the WordPress admin to generate hreflang annotations.</p>
                        </div>
                    </xsl:when>
                    <xsl:otherwise>
                        <table class="cannyforge-hreflang-sitemap-table">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Language</th>
                                    <th>Alternate versions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <xsl:for-each select="$cluster-heads">
                                    <xsl:sort select="substring-after(normalize-space(string(preceding-sibling::comment()[1])), 'cannyforge-hreflang-group:')" />
                                    <xsl:variable name="slug" select="substring-after(normalize-space(string(preceding-sibling::comment()[1])), 'cannyforge-hreflang-group:')" />
                                    <tr class="cannyforge-hreflang-sitemap-group-sep">
                                        <td colspan="3">
                                            <span class="cannyforge-hreflang-sitemap-group-sep-inner">
                                                <span class="cannyforge-hreflang-sitemap-group-label">Group:</span>
                                                <strong class="cannyforge-hreflang-sitemap-group-name"><xsl:value-of select="$slug" /></strong>
                                                <xsl:if test="xhtml:link[@hreflang = 'x-default']">
                                                    <span class="cannyforge-hreflang-sitemap-group-x-default">
                                                        <span class="cannyforge-hreflang-sitemap-group-x-default-label">x-default</span>
                                                        <a href="{xhtml:link[@hreflang = 'x-default']/@href}" class="cannyforge-hreflang-sitemap-group-x-default-link" target="_blank" rel="noopener noreferrer"><xsl:value-of select="xhtml:link[@hreflang = 'x-default']/@href" /></a>
                                                    </span>
                                                </xsl:if>
                                            </span>
                                        </td>
                                    </tr>
                                    <xsl:for-each select="key('cf-sitemap-urls-by-group', $slug)">
                                        <xsl:sort select="normalize-space(string(sitemap:loc))" />
                                        <xsl:variable name="loc" select="normalize-space(string(sitemap:loc))" />
                                        <xsl:variable name="lang">
                                            <xsl:choose>
                                                <xsl:when test="xhtml:link[@href = $loc]">
                                                    <xsl:value-of select="xhtml:link[@href = $loc]/@hreflang" />
                                                </xsl:when>
                                                <xsl:when test="xhtml:link[@hreflang != 'x-default'][1]">
                                                    <xsl:value-of select="xhtml:link[@hreflang != 'x-default'][1]/@hreflang" />
                                                </xsl:when>
                                                <xsl:otherwise>
                                                    <xsl:value-of select="xhtml:link[1]/@hreflang" />
                                                </xsl:otherwise>
                                            </xsl:choose>
                                        </xsl:variable>
                                        <tr>
                                            <td class="cannyforge-hreflang-sitemap-url-cell">
                                                <a href="{sitemap:loc}" target="_blank" rel="noopener noreferrer"><xsl:value-of select="sitemap:loc" /></a>
                                            </td>
                                            <td>
                                                <span class="cannyforge-hreflang-sitemap-tag">
                                                    <xsl:if test="$lang = 'x-default'">
                                                        <xsl:attribute name="class">cannyforge-hreflang-sitemap-tag cannyforge-hreflang-sitemap-tag-x-default</xsl:attribute>
                                                    </xsl:if>
                                                    <xsl:value-of select="$lang" />
                                                </span>
                                            </td>
                                            <td>
                                                <div class="cannyforge-hreflang-sitemap-alternates">
                                                    <xsl:for-each select="xhtml:link[@hreflang != 'x-default']">
                                                        <a href="{@href}" class="cannyforge-hreflang-sitemap-alternate" target="_blank" rel="noopener noreferrer">
                                                            <span class="cannyforge-hreflang-sitemap-alternate-code"><xsl:value-of select="@hreflang" /></span>
                                                        </a>
                                                    </xsl:for-each>
                                                </div>
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </xsl:for-each>
                            </tbody>
                        </table>
                    </xsl:otherwise>
                </xsl:choose>
                </div>
            </div>
        </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
