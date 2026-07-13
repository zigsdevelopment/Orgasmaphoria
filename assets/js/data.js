window.ORG_DATA = {
  site: {
    name: "Orgasmaphoria",
    tagline: "Music, mystery, connection.",
    spotifyArtist: "https://open.spotify.com/artist/7JPxqyyzIP3N4YChFOtFvC?si=XERgnpYLQGC4KQZBFIPNdQ",
    spotifyEmbed: "https://open.spotify.com/embed/artist/7JPxqyyzIP3N4YChFOtFvC?utm_source=generator&si=acb54b5af26f4f12",
    contactEmail: "",
    formEndpoint: "",
    supportHours: "Messages are reviewed during normal business hours.",
    matureNotice: "This community is intended for adults 18 and older."
  },
  tiers: [
    {
      id: "listener",
      name: "Listener",
      level: 1,
      price: "Free",
      description: "A simple account for public releases, saved items, event news, and community updates.",
      features: ["Save music and public resources", "Follow public events", "Create a community profile", "Member directory access"]
    },
    {
      id: "inner",
      name: "Inner Circle",
      level: 2,
      price: "Pricing set at launch",
      description: "Expanded access to member documents, private listening rooms, activities, and discussions.",
      features: ["Everything in Listener", "Member-only library", "Private invitations", "Direct member messaging", "Early access previews"]
    },
    {
      id: "patron",
      name: "Velvet Patron",
      level: 3,
      price: "Pricing set at launch",
      description: "The highest member tier for collectors, supporters, and the most complete community access.",
      features: ["Everything in Inner Circle", "Patron-only releases", "Collector downloads", "Priority event access", "Supporter recognition controls"]
    }
  ],
  users: [
    {
      id: "demo-member",
      username: "morganrose",
      displayName: "Morgan Rose",
      initials: "MR",
      tier: "inner",
      role: "member",
      city: "Tampa, FL",
      joined: "May 2026",
      bio: "Music listener, reader, and fan of thoughtful conversation. I am usually building a playlist or planning my next book night.",
      interests: ["Music", "Books", "Listening salons", "Creative writing"],
      online: true,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "demo-patron",
      username: "velvetatlas",
      displayName: "Alex Rowan",
      initials: "AR",
      tier: "patron",
      role: "member",
      city: "Savannah, GA",
      joined: "March 2026",
      bio: "Collector of atmospheric music and visual art. Interested in community events, creative projects, and long-form interviews.",
      interests: ["Art", "Interviews", "Collector releases", "Events"],
      online: false,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "demo-staff",
      username: "studioadmin",
      displayName: "Studio Admin",
      initials: "SA",
      tier: "staff",
      role: "staff",
      city: "Orgasmaphoria Studio",
      joined: "January 2026",
      bio: "Official staff account used for community support, content publishing, and event coordination.",
      interests: ["Community support", "Publishing", "Events"],
      online: true,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "u-avery",
      username: "nocturnenotes",
      displayName: "Avery Lane",
      initials: "AL",
      tier: "listener",
      role: "member",
      city: "Atlanta, GA",
      joined: "June 2026",
      bio: "Late-night listener and amateur pianist. Here for new music and thoughtful community discussions.",
      interests: ["Piano", "New releases", "Discussion"],
      online: true,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "u-riley",
      username: "paperlantern",
      displayName: "Riley Quinn",
      initials: "RQ",
      tier: "inner",
      role: "member",
      city: "Charlotte, NC",
      joined: "April 2026",
      bio: "Book club host, journal collector, and believer in clear communication.",
      interests: ["Books", "Journaling", "Hosting"],
      online: false,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "u-cameron",
      username: "violethour",
      displayName: "Cameron Vale",
      initials: "CV",
      tier: "patron",
      role: "member",
      city: "Richmond, VA",
      joined: "February 2026",
      bio: "Photographer and event enthusiast. I enjoy artist interviews and behind-the-scenes production notes.",
      interests: ["Photography", "Events", "Production"],
      online: true,
      profileVisibility: "members",
      allowMessages: "members"
    },
    {
      id: "u-jordan",
      username: "softstatic",
      displayName: "Jordan Ellis",
      initials: "JE",
      tier: "inner",
      role: "member",
      city: "Nashville, TN",
      joined: "May 2026",
      bio: "Sound design student and playlist maker. Always interested in how atmosphere changes a story.",
      interests: ["Sound design", "Playlists", "Storytelling"],
      online: false,
      profileVisibility: "members",
      allowMessages: "nobody"
    }
  ],
  library: [
    {
      id: "lib-cards",
      title: "Signals & Stories",
      subtitle: "Printable conversation card game",
      description: "Twelve thoughtful prompts for adult groups, book nights, listening salons, and creative gatherings.",
      type: "Game",
      format: "PDF",
      access: "inner",
      accessLabel: "Inner Circle",
      file: "documents/signals-and-stories-card-game.pdf",
      tags: ["conversation", "printable", "activity"],
      featured: true,
      added: "2026-07-10"
    },
    {
      id: "lib-salon",
      title: "The Listening Salon",
      subtitle: "Host guide and activity plan",
      description: "A practical guide for creating a respectful, accessible, music-centered gathering.",
      type: "Activity",
      format: "PDF",
      access: "listener",
      accessLabel: "All members",
      file: "documents/listening-salon-host-guide.pdf",
      tags: ["hosting", "music", "events"],
      featured: true,
      added: "2026-07-11"
    },
    {
      id: "lib-journal",
      title: "Midnight Pages",
      subtitle: "Reflection workbook sample",
      description: "A calm, printable journal with prompts on creativity, communication, atmosphere, and personal direction.",
      type: "Workbook",
      format: "PDF",
      access: "inner",
      accessLabel: "Inner Circle",
      file: "documents/midnight-pages-journal-sample.pdf",
      tags: ["journal", "writing", "reflection"],
      featured: false,
      added: "2026-07-12"
    },
    {
      id: "lib-invite",
      title: "After Dark Listening Salon",
      subtitle: "Sample member invitation",
      description: "A downloadable invitation for the sample private listening event shown on the Events page.",
      type: "Invitation",
      format: "PDF",
      access: "inner",
      accessLabel: "Inner Circle",
      file: "documents/after-dark-listening-salon-invite.pdf",
      tags: ["invite", "event", "listening"],
      featured: false,
      added: "2026-07-12"
    },
    {
      id: "lib-staff",
      title: "Release & Publishing Checklist",
      subtitle: "Internal operations template",
      description: "A sample staff checklist covering content, website, community, and privacy review before publishing.",
      type: "Staff document",
      format: "PDF",
      access: "staff",
      accessLabel: "Staff only",
      file: "documents/staff-release-checklist.pdf",
      tags: ["staff", "publishing", "operations"],
      featured: false,
      added: "2026-07-12"
    }
  ],
  events: [
    {
      id: "event-salon",
      title: "After Dark Listening Salon",
      date: "2026-10-17",
      time: "8:00 PM - 10:00 PM ET",
      location: "Private online room",
      access: "inner",
      accessLabel: "Inner Circle and Patron",
      description: "A guided listening session with artist notes, quiet reflection, moderated discussion, and a closing Q&A.",
      capacity: 60,
      attending: 34,
      invite: "documents/after-dark-listening-salon-invite.pdf",
      calendar: "invites/after-dark-listening-salon.ics"
    },
    {
      id: "event-studio",
      title: "Behind the Song: Studio Notes",
      date: "2026-11-05",
      time: "7:30 PM - 8:30 PM ET",
      location: "Member livestream",
      access: "listener",
      accessLabel: "All members",
      description: "A public-to-members discussion about songwriting choices, atmosphere, and building a release from idea to final mix.",
      capacity: 150,
      attending: 78,
      invite: "",
      calendar: "invites/behind-the-song.ics"
    },
    {
      id: "event-circle",
      title: "Midnight Pages Book Circle",
      date: "2026-11-21",
      time: "8:00 PM - 9:15 PM ET",
      location: "Private community room",
      access: "patron",
      accessLabel: "Velvet Patron",
      description: "A smaller discussion gathering centered on a selected romantic suspense title and its use of voice, trust, and mystery.",
      capacity: 24,
      attending: 12,
      invite: "",
      calendar: "invites/midnight-pages-book-circle.ics"
    }
  ],
  products: [
    {
      id: "prod-cards",
      title: "Signals & Stories Card Game",
      category: "Digital game",
      description: "A printable expanded edition with 48 prompts and four themed conversation sets.",
      price: 7,
      format: "PDF download",
      accessNote: "Separate purchase. Membership not required.",
      demo: true
    },
    {
      id: "prod-anthology",
      title: "Midnight Pages Digital Anthology",
      category: "Digital book",
      description: "A sample listing for a future collection of short essays, reflections, and creative prompts.",
      price: 12,
      format: "EPUB and PDF",
      accessNote: "Separate purchase with member discount support.",
      demo: true
    },
    {
      id: "prod-salon",
      title: "Listening Salon Event Pass",
      category: "Event access",
      description: "A one-time ticket listing for events that are sold separately from recurring memberships.",
      price: 15,
      format: "Digital ticket",
      accessNote: "One-time purchase. Event eligibility still applies.",
      demo: true
    },
    {
      id: "prod-bundle",
      title: "Velvet Collector Digital Bundle",
      category: "Bundle",
      description: "A sample bundle for music, artwork, a digital booklet, and bonus commentary files.",
      price: 24,
      format: "ZIP download",
      accessNote: "Separate purchase with patron pricing support.",
      demo: true
    }
  ],
  conversations: [
    {
      id: "conv-1",
      participants: ["demo-member", "u-riley"],
      messages: [
        { id: "m1", sender: "u-riley", text: "Hi Morgan. Are you planning to attend the listening salon?", sentAt: "2026-07-11T18:14:00Z" },
        { id: "m2", sender: "demo-member", text: "Yes, I saved it. I am especially interested in the discussion format.", sentAt: "2026-07-11T18:22:00Z" }
      ]
    },
    {
      id: "conv-2",
      participants: ["demo-member", "demo-staff"],
      messages: [
        { id: "m3", sender: "demo-staff", text: "Welcome to the Inner Circle demo. Your member library is ready to explore.", sentAt: "2026-07-10T15:05:00Z" }
      ]
    }
  ]
};
