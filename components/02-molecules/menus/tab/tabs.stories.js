import React from 'react';
import { storiesOf } from '@storybook/react';

import tabs from './tabs.twig'
const tabsPrimaryTwig = (
  tabs({
    tabs: {
      1: {
        tab_text: "Tab 1",
        tab_content: "A number of connected items or names written or printed consecutively, typically one below the other."
      },
      2: {
        tab_text: "Tab 2",
        tab_content: "A number of connected items or names written or printed consecutively, typically one below the other."
      },
      3: {
        tab_text: "Tab 3",
        tab_content: "A number of connected items or names written or printed consecutively, typically one below the other."
      }
    }
  })
)

/**
 * Add storybook definition for Lists.
 */
storiesOf('Molecules/TabsPrimary', module)
  .add('Primary Tabs', () => (
    <div dangerouslySetInnerHTML={{__html: tabsPrimaryTwig}}></div>
  ))
