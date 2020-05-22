---
layout: default
parent: Getting Started
nav_order: 1
---
# Creating your first page
{: .no_toc }

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Create a new Layout Resource

Resource Endpoint: `/_/layouts`

##### Sample post request:

```json
{
  "reference": "primary",
  "uiComponent": "PrimaryLayout",
  "classNames": ["has-border"]
}
```

This is a very simple component, the reference must be unique and the uiComponent is the name that your front-end application will recognise to display the layout and position the rendered page within it.

## Create a new Page resource

Resource endpoint: `/_/pages`

A page, much like a layout, will define which UI component to use to structure the layout of the page. For example, you may have a UI component which will have 2 component collections to display your final UI components into 2 columns.
