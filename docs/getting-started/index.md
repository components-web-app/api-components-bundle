---
layout: default
nav_order: 2
has_children: true
---
# Getting Started
{: .no_toc }
This bundle introduces a number of core resources which enables you to define your web application's structure.

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Understanding the basics

The main principle of this data structure is to allow you to configure which UI components should be displayed on any given page. To do this, it is important you understand our terminology.

### The Resource Structure
1. As an API user you will request a `Route` resource which will have a `Page` resource.
1. The `Page` resource will use a `Layout` resource which can be used across multiple pages.
1. Both `Page` and `Layout` resources will contain (possibly more than one) `ComponentCollection` resource(s) to group your components into.
1. Within the collection you will have many `ComponentPosition` resources which define which component is displayed within it, and the order in which to display it.
1. You will create the component resources by extending `AbstractComponent`.

### What is UI Component?
**This is not a resource in the API.** When we refer to the UI component (abbreviation of User-Interface Component), we just mean the name by which the component in your front-end application will be named. For example, you may have made a component called `NavigationBar` in the front-end. When it is referred to in the context of an API Component resource (see below), the API resource/class name will usually be the same as the name defined in the front-end application, but this can be changed. E.g. [VueJS Components](https://vuejs.org/v2/guide/components.html)

### What is a Component resource?
A component as an API resource will define which reusable UI component you have created in your front-end application and usually the data associated with it. An example output could be the following.
```json
{
    "@context": "/contexts/HtmlContent",
    "@id": "/component/html_contents/0a1b1d75c1114be285b037b4f8e0d6c4",
    "@type": "HtmlContent",
    "_metadata": {
        "persisted": true
    },
    "html": "<p>Hello world</p>"
}
```
You will have made this API resource which is very easy, as we will show later, and your front-end application will have sent a POST request to create it. The front-end application should then look for the UI component named `HTMLContent` to display. There is an optional property `uiComponent` where you can explicitly define the name of the front-end UI Component to display.

### What is a ComponentPosition resource?
You can locate a component within Component Collections (which we will talk about in a moment). This is simply a resource that determines collection(s) the component is rendered, and the position within it.

### What is a ComponentCollection resource?
This is simply a group of ComponentPosition resources. A collection must have a reference so that the front-end application knows which collection to place where within the structure of a given page or layout.

For example, in a layout you may have a ComponentCollection which you can create with the reference `header` so your UI component can be configured to render a Navigation component within a header area.

### What is a Page resource?
This is a structure for the main area of a page. This should not include a layout which is re-used across multiple pages. Component groups will be located within a page. For example the page may display a 2-column layout and there would be a ComponentCollection for each column. You could give the collections the references `left-column` and `right-column` in this instance. The page must specify which UI component to use.

### What is a Layout resource?
This is very similar to a `Page` where it will primarily define which UI component to use for the layout which will be re-used across multiple pages. It will usually also contain `ComponentCollection` resources.


