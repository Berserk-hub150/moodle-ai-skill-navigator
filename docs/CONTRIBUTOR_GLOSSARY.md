# Contributor glossary

This glossary explains common Moodle and AI terms used in AI Skill Navigator.

## Block plugin

A Moodle plugin that adds a small tool or information panel to a page, often in a course sidebar. AI Skill Navigator includes an optional block plugin that links users to its tools. [Moodle block plugins](https://moodledev.io/docs/5.3/apis/plugintypes/blocks)

## Capability

A named Moodle permission that controls whether a user can perform an action, such as viewing a feature or managing settings. Capabilities are assigned through roles. [Moodle Roles API](https://moodledev.io/docs/5.3/apis/subsystems/roles)

## Chunk

A small section of a larger document. RAG systems split course material into chunks so they can find the parts most relevant to a question. [OpenAI vector-store files](https://platform.openai.com/docs/api-reference/vector-stores-files)

## Context

The Moodle location where permissions apply, such as the whole site, a course, an activity, or a block. A user can have different permissions in different contexts. [Moodle Roles API](https://moodledev.io/docs/5.3/apis/subsystems/roles)

## Embedding

A numeric representation of text that helps software compare meaning. Embeddings help a RAG system find course-material chunks related to a question.

## Language string

A reusable piece of interface text, such as a button label or message, stored separately so Moodle can translate it into other languages. [Moodle plugin files](https://docs.moodle.org/dev/Plugin_files)

## Local plugin

A Moodle plugin used for site-wide or custom functionality that does not fit a more specific plugin type. AI Skill Navigator’s main plugin is a local plugin. [Moodle plugin types](https://moodledev.io/docs/5.3/apis/plugintypes)

## Provider

A service or implementation that supplies a feature to the plugin. For example, an AI provider can generate responses, and an embedding provider can prepare text for semantic search.

## RAG

Retrieval-Augmented Generation. A method where the system finds relevant course material first, then gives it to an AI model to help produce a more relevant answer.

## Sesskey

A Moodle security token included with requests that change data. It helps protect users from unwanted actions submitted through their browser. [Moodle plugin contribution checklist](https://docs.moodle.org/dev/Plugin_contribution_checklist)
