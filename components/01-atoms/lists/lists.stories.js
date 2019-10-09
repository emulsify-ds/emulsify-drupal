import React from 'react';
import { storiesOf } from '@storybook/react';

import dl from './dl.twig'
const dlTwig = (
  dl({
    dl_items: {
      1: {
        dl_term: "Definition List",
        dl_def: "A number of connected items or names written or printed consecutively, typically one below the other."
      },
      2: {
        dl_term: "This is a term.",
        dl_def: "This is the definition of that term, which both live in a <code>dl</code>."
      },
      3: {
        dl_term: "Here is another term.",
        dl_def: "And it gets a definition too, which is this line."
      },
      4: {
        dl_term: "Here is one last term.",
        dl_def: "With the final definition."
      }
    }
  })
)

import ul from './ul.twig'
const ulTwig = (
  ul({
    ul_items: {
      1: {
        content: "This is the first item in the unordered list."
      },
      2: {
        label: "This is the optional label",
        content: "And here is the item that goes with the label."
      },
      3: {
        content: "Here's the third item."
      },
      4: {
        content: "And here's the last item."
      }
    }
  })
)

import ol from './ol.twig'
const olTwig = (
  ol({
    ol_items: {
      1: {
        content: "This is the first item in the ordered list."
      },
      2: {
        label: "This is the optional label",
        content: "And here is the item that goes with the label."
      },
      3: {
        content: "Here's the third item."
      },
      4: {
        content: "And here's the last item."
      }
    }
  })
)

/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Lists', module)
  .add('Definition List', () => (
    <div dangerouslySetInnerHTML={{__html: dlTwig}}></div>
  ))
  .add('Unordered List', () => (
    <div dangerouslySetInnerHTML={{__html: ulTwig}}></div>
  ))
  .add('Ordered List', () => (
    <div dangerouslySetInnerHTML={{__html: olTwig}}></div>
  ))
