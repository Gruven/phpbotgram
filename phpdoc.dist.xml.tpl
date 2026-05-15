<?xml version="1.0" encoding="UTF-8"?>
<phpdocumentor configVersion="3" xmlns="https://www.phpdoc.org">
  <title>phpbotgram</title>
  <paths>
    <output>build/docs/api</output>
    <cache>build/docs/.cache</cache>
  </paths>
  <version number="${VERSION}">
    <api format="php">
      <source dsn="."><path>src</path></source>
      <ignore-tags>
        <!-- Preserved from Phase 9: codegen-output classes carry
             @generated; suppress to keep API docs readable. Per the
             v3 XSD, <ignore-tags> is a child of <api>, not <version>. -->
        <ignore-tag>generated</ignore-tag>
      </ignore-tags>
    </api>
    <guide format="md" output="guide">
      <source dsn="."><path>docs/guide/en</path></source>
    </guide>
  </version>
  <!-- Template overrides live at .phpdoc/template/ (next to this XML),
       auto-discovered by phpDocumentor's
       ProvideTemplateOverridePathMiddleware. -->
</phpdocumentor>
